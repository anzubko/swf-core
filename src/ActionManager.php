<?php declare(strict_types=1);

namespace SWF;

use Composer\Autoload\ClassLoader;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;
use function count;
use function is_array;

final class ActionManager
{
    private string $metricsFile = APP_DIR . '/var/cache/.swf/action.manager.metrics.php';

    private string $lockKey = '.swf/action.manager';

    /**
     * @var AbstractActionProcessor[]
     */
    private array $processors;

    /**
     * @var string[]
     */
    private array $namespaces;

    /**
     * @var array<class-string<AbstractActionProcessor>, ActionCache>
     */
    private array $caches;

    /**
     * @var array<string, int>
     */
    private array $classesFiles;

    private static self $instance;

    private function __construct()
    {
        $this->processors = [
            new CommandProcessor(),
            new ControllerProcessor(),
            new ListenerProcessor(),
            new RelationProcessor(),
        ];

        $this->namespaces = config('system')->get('namespaces');
    }

    /**
     * @throws LogicException
     * @throws RuntimeException
     */
    public static function getInstance(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
            self::$instance->checkMetricsAndReadCaches();
        }

        return self::$instance;
    }

    /**
     * @param class-string<AbstractActionProcessor> $className
     */
    public function getCache(string $className): ?ActionCache
    {
        return isset($this->caches) ? ($this->caches[$className] ?? null) : null;
    }

    /**
     * @throws LogicException
     * @throws RuntimeException
     */
    private function checkMetricsAndReadCaches(): void
    {
        if (config('system')->get('env') === 'prod' && $this->readCaches()) {
            return;
        }

        $metrics = $this->getMetrics();
        if (null !== $metrics && !$this->isOutdated($metrics) && $this->readCaches()) {
            return;
        }

        FileLocker::getInstance()->acquire($this->lockKey);

        if (null === $this->getMetrics($metrics) || !$this->readCaches()) {
            $this->rebuild();
        }

        FileLocker::getInstance()->release($this->lockKey);
    }

    /**
     * @param mixed[]|null $oldMetrics
     *
     * @return mixed[]|null
     */
    private function getMetrics(?array $oldMetrics = null): ?array
    {
        $metrics = @include $this->metricsFile;
        if (!is_array($metrics) || $metrics === $oldMetrics) {
            return null;
        }

        return $metrics;
    }

    /**
     * @param mixed[] $metrics
     *
     * @throws RuntimeException
     */
    private function isOutdated(array $metrics): bool
    {
        $this->scanForClassesFiles();

        if (count($this->classesFiles) !== $metrics['count']) {
            return true;
        }

        foreach ($this->classesFiles as $mTime) {
            if ($mTime > $metrics['time']) {
                return true;
            }
        }

        return false;
    }

    private function readCaches(): bool
    {
        $caches = [];
        foreach ($this->processors as $processor) {
            $cache = @include $processor->getCacheFile();
            if (!is_array($cache)) {
                return false;
            }

            $caches[$processor::class] = new ActionCache($cache);
        }

        $this->caches = $caches;

        return true;
    }

    /**
     * @throws LogicException
     * @throws RuntimeException
     */
    private function rebuild(): void
    {
        $this->scanForClassesFiles();

        foreach ($this->classesFiles as $file => $mTime) {
            require_once $file;
        }

        $classes = new ActionClasses();
        foreach (get_declared_classes() as $className) {
            if (!$this->isNsAllowed($className)) {
                continue;
            }

            $class = new ReflectionClass($className);
            if (!$class->isInstantiable()) {
                continue;
            }

            $classes->list[] = $class;
        }

        $caches = [];
        foreach ($this->processors as $processor) {
            $caches[$processor::class] = $processor->buildCache($classes);

            $processor->saveCache($caches[$processor::class]);
        }

        $metrics = [
            'time' => time(),
            'count' => count($this->classesFiles),
            'hash' => TextHandler::random(),
        ];

        if (!FileHandler::putVar($this->metricsFile, $metrics)) {
            throw new RuntimeException(sprintf('Unable to write file %s', $this->metricsFile));
        }

        $this->caches = $caches;
    }

    /**
     * @throws RuntimeException
     */
    private function scanForClassesFiles(): void
    {
        if (isset($this->classesFiles)) {
            return;
        }

        $loader = $this->getLoader();

        $this->classesFiles = [];
        foreach ($loader->getPrefixesPsr4() + $loader->getPrefixes() as $ns => $dirs) {
            if (!$this->isNsAllowed($ns)) {
                continue;
            }

            foreach ($dirs as $dir) {
                /** @var RecursiveDirectoryIterator $info */
                foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $info) {
                    if (!$info->isFile() || false === $info->getMTime() || 'php' !== $info->getExtension()) {
                        continue;
                    }

                    $this->classesFiles[$info->getPathname()] = $info->getMTime();
                }
            }
        }
    }

    /**
     * @throws RuntimeException
     */
    private function getLoader(): ClassLoader
    {
        foreach (get_declared_classes() as $className) {
            if (!str_starts_with($className, 'ComposerAutoloaderInit')) {
                continue;
            }

            $loaderGetter = sprintf('%s::getLoader', $className);
            if (is_callable($loaderGetter)) {
                return $loaderGetter();
            }
        }

        throw new RuntimeException('Unable to find composer loader');
    }

    private function isNsAllowed(string $ns): bool
    {
        foreach ($this->namespaces as $namespace) {
            if (str_starts_with($ns, $namespace)) {
                return true;
            }
        }

        return false;
    }
}
