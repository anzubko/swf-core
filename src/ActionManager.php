<?php declare(strict_types=1);

namespace SWF;

use App\Config\SystemConfig;
use Composer\Autoload\ClassLoader;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;
use function count;
use function in_array;
use function is_array;
use function strlen;

final class ActionManager
{
    private const METRICS_FILE = APP_DIR . '/var/cache/.swf/actions.metrics.php';

    private const LOCK_KEY = '.swf/action.manager';

    private const NAMESPACE_SEPARATOR = '\\';

    /**
     * @var AbstractActionProcessor[]
     */
    private array $processors;

    /**
     * @var array<class-string<AbstractActionProcessor>, ActionCache>
     */
    private array $caches;

    /**
     * @var array<array{class:string, file:string, modified:int}>
     */
    private array $classesInfo;

    /**
     * @throws LogicException
     * @throws RuntimeException
     */
    public function __construct()
    {
        $this->processors = [
            new CommandProcessor(),
            new ControllerProcessor(),
            new ListenerProcessor(),
            new RelationProcessor(),
        ];

        if ($this->readCaches()) {
            if ('prod' === i(SystemConfig::class)->env) {
                return;
            }

            $metrics = $this->getMetrics();
            if (null !== $metrics && !$this->isOutdated($metrics)) {
                return;
            }
        } else {
            $metrics = null;
        }

        i(FileLocker::class)->acquire(self::LOCK_KEY);

        if (null === $this->getMetrics($metrics) || !$this->readCaches()) {
            $this->rebuild();
        }

        i(FileLocker::class)->release(self::LOCK_KEY);
    }

    /**
     * @param class-string<AbstractActionProcessor> $className
     */
    public function getCache(string $className): ?ActionCache
    {
        return $this->caches[$className] ?? null;
    }

    /**
     * @param mixed[]|null $oldMetrics
     *
     * @return mixed[]|null
     */
    private function getMetrics(?array $oldMetrics = null): ?array
    {
        $metrics = @include self::METRICS_FILE;
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
        $this->classesInfo ??= $this->getClassesInfo();
        if (count($this->classesInfo) !== $metrics['count']) {
            return true;
        }

        foreach ($this->classesInfo as $classInfo) {
            if ($classInfo['modified'] > $metrics['modified']) {
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
        $this->caches = [];

        $this->classesInfo ??= $this->getClassesInfo();
        foreach ($this->classesInfo as $info) {
            class_exists($info['class']);
        }

        $classes = new ActionClasses();
        foreach (get_declared_classes() as $className) {
            if (!TextHandler::startsWith($className, i(SystemConfig::class)->allowedNsPrefixes)) {
                continue;
            }

            $class = new ReflectionClass($className);
            if ($class->isInstantiable()) {
                $classes->list[] = $class;
            }
        }

        $caches = [];
        foreach ($this->processors as $processor) {
            $caches[$processor::class] = $processor->buildCache($classes);

            $processor->saveCache($caches[$processor::class]);
        }

        if (
            !FileHandler::putVar(self::METRICS_FILE, [
                'modified' => time(),
                'count' => count($this->classesInfo),
                'hash' => TextHandler::random(),
            ])
        ) {
            throw new RuntimeException(sprintf('Unable to write file %s', self::METRICS_FILE));
        }

        $this->caches = $caches;
    }

    /**
     * @return array<array{class:string, file:string, modified:int}>
     *
     * @throws RuntimeException
     */
    private function getClassesInfo(): array
    {
        $allowedNsRoots = [];
        foreach (i(SystemConfig::class)->allowedNsPrefixes as $nsPrefix) {
            $chunks = explode(self::NAMESPACE_SEPARATOR, $nsPrefix, 2);
            if (count($chunks) > 1) {
                $allowedNsRoots[] = $chunks[0] . self::NAMESPACE_SEPARATOR;
            } else {
                $allowedNsRoots[] = $nsPrefix;
            }
        }

        $classes = [];
        foreach ($this->getLoader()->getPrefixesPsr4() as $namespace => $dirs) {
            if (!TextHandler::startsWith($namespace, $allowedNsRoots)) {
                continue;
            }

            foreach ($dirs as $dir) {
                /** @var RecursiveDirectoryIterator $info */
                foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $info) {
                    if (!$info->isFile() || false === $info->getMTime() || !preg_match('/^[A-Z][A-Za-z\d]*\.php$/', $info->getFilename())) {
                        continue;
                    }

                    $className = $namespace . strtr(substr($info->getPathname(), strlen($dir) + 1, -4), DIRECTORY_SEPARATOR, self::NAMESPACE_SEPARATOR);
                    if (!TextHandler::startsWith($className, i(SystemConfig::class)->allowedNsPrefixes)) {
                        continue;
                    }

                    $classes[] = [
                        'class' => $className,
                        'file' => $info->getPathname(),
                        'modified' => $info->getMTime(),
                    ];
                }
            }
        }

        return $classes;
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

            $loaderGetter = [$className, 'getLoader'];
            if (is_callable($loaderGetter)) {
                return $loaderGetter();
            }
        }

        throw new RuntimeException('Unable to find composer loader');
    }
}
