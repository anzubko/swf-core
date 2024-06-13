<?php declare(strict_types=1);

namespace SWF;

use Composer\Autoload\ClassLoader;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use function count;
use function is_array;

final class ActionManager
{
    private string $metricsFile = APP_DIR . '/var/cache/.swf/actions.metrics.php';

    private string $lockKey = '.swf.action.manager';

    /**
     * @var AbstractActionProcessor[]
     */
    private array $processors;

    /**
     * @var array<class-string<AbstractActionProcessor>, ActionCache>
     */
    private array $caches;

    /**
     * @var string[]
     */
    private array $namespaces;

    /**
     * @var array<string, int>
     */
    private array $classesFiles;

    private static self $instance;

    /**
     * @throws LogicException
     * @throws RuntimeException
     */
    private function __construct()
    {
        $this->processors = [
            new CommandProcessor(),
            new ControllerProcessor(),
            new ListenerProcessor(),
        ];

        $this->namespaces = config('system')->get('namespaces');

        if (!$this->isOutdated() && $this->readCaches()) {
            return;
        }

        LocalLocker::getInstance()->acquire($this->lockKey);

        if (!$this->readCaches()) {
            $this->rebuild();
        }

        LocalLocker::getInstance()->release($this->lockKey);
    }

    /**
     * @throws LogicException
     * @throws RuntimeException
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * @param class-string<AbstractActionProcessor> $className
     */
    public function getCache(string $className): ActionCache
    {
        return $this->caches[$className];
    }

    /**
     * @throws RuntimeException
     */
    private function isOutdated(): bool
    {
        if (config('system')->get('env') === 'prod') {
            return false;
        }

        $metrics = @include $this->metricsFile;
        if (!is_array($metrics)) {
            return true;
        }

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
        $this->caches = [];
        foreach ($this->processors as $processor) {
            $cache = @include $processor->getCacheFile();
            if (!is_array($cache)) {
                $this->caches = [];

                return false;
            }

            $this->caches[$processor::class] = new ActionCache($cache);
        }

        return true;
    }

    /**
     * @throws RuntimeException
     */
    private function rebuild(): void
    {
        $this->scanForClassesFiles();

        foreach ($this->classesFiles as $file => $mTime) {
            require_once $file;
        }

        $this->caches = [];
        foreach ($this->processors as $processor) {
            $this->caches[$processor::class] = $processor->initializeCache();
        }

        foreach (get_declared_classes() as $className) {
            if (!$this->isNsAllowed($className)) {
                continue;
            }

            foreach ((new ReflectionClass($className))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($this->processors as $processor) {
                    $processor->processMethod($this->caches[$processor::class], $method);
                }
            }
        }

        foreach ($this->processors as $processor) {
            $processor->finalizeCache($this->caches[$processor::class]);
            $processor->saveCache($this->caches[$processor::class]);
        }

        if (!FileHandler::putVar($this->metricsFile, ['time' => time(), 'count' => count($this->classesFiles)])) {
            throw new RuntimeException(sprintf('Unable to write file %s', $this->metricsFile));
        }
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
        foreach ($this->namespaces as $allowedNs) {
            if (str_starts_with($ns, $allowedNs)) {
                return true;
            }
        }

        return false;
    }
}
