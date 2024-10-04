<?php declare(strict_types=1);

namespace SWF;

use Composer\Autoload\ClassLoader;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use function count;
use function is_array;
use function strlen;

/**
 * @internal
 */
final class ActionManager
{
    private const RELATIVE_METRICS_FILE = '/.system/actions.metrics.php';

    private const LOCK_KEY = '.system/action.manager';

    private const PROCESSOR_CLASS_NAMES = [
        CommandProcessor::class,
        ControllerProcessor::class,
        ListenerProcessor::class,
        RelationProcessor::class,
    ];

    /**
     * @var array<class-string<AbstractActionProcessor>, AbstractActionProcessor>
     */
    private array $processors = [];

    public function __construct()
    {
        foreach (self::PROCESSOR_CLASS_NAMES as $processorClassName) {
            $this->processors[$processorClassName] = i($processorClassName);
        }
    }

    /**
     * @throws LogicException
     * @throws RuntimeException
     */
    public function prepare(): void
    {
        foreach ($this->readOrRebuildCaches() as $processorClassName => $cache) {
            $this->processors[$processorClassName]->putCacheToStorage($cache);
        }
    }

    /**
     * @return array<class-string<AbstractActionProcessor>, mixed[]>
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    private function readOrRebuildCaches(): array
    {
        $caches = $this->readCaches();
        if (null !== $caches && 'prod' === ConfigStorage::$system->env) {
            return $caches;
        }

        $metrics = null;
        if (null !== $caches) {
            $metrics = $this->getMetrics();
            if (null !== $metrics && !$this->isOutdated($metrics)) {
                return $caches;
            }
        }

        i(FileLocker::class)->acquire(self::LOCK_KEY);

        try {
            if (null === $this->getMetrics($metrics)) {
                $caches = $this->rebuild();
            } else {
                $caches = $this->readCaches();
                if (null === $caches) {
                    $caches = $this->rebuild();
                }
            }
        } finally {
            i(FileLocker::class)->release(self::LOCK_KEY);
        }

        return $caches;
    }

    /**
     * @return array<class-string<AbstractActionProcessor>, mixed[]>|null
     */
    private function readCaches(): ?array
    {
        $caches = [];
        foreach ($this->processors as $processor) {
            $cache = @include $processor->getCacheFile();
            if (!is_array($cache)) {
                return null;
            }

            $caches[$processor::class] = $cache;
        }

        return $caches;
    }

    /**
     * @param mixed[]|null $oldMetrics
     *
     * @return mixed[]|null
     */
    private function getMetrics(?array $oldMetrics = null): ?array
    {
        $metrics = @include $this->getMetricsFile();
        if (!is_array($metrics) || $metrics === $oldMetrics) {
            return null;
        }

        return $metrics;
    }

    private function getMetricsFile(): string
    {
        return ConfigStorage::$system->cacheDir . self::RELATIVE_METRICS_FILE;
    }

    /**
     * @param mixed[] $metrics
     *
     * @throws RuntimeException
     */
    private function isOutdated(array $metrics): bool
    {
        if (count($this->getClassesInfo()) !== $metrics['count']) {
            return true;
        }

        foreach ($this->getClassesInfo() as $modified) {
            if ($modified > $metrics['modified']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Method can generate not catchable fatal errors.
     *
     * @return array<class-string<AbstractActionProcessor>, mixed[]>
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    private function rebuild(): array
    {
        foreach ($this->getClassesInfo() as $className => $modified) {
            class_exists($className);
        }

        $classes = [];
        foreach (get_declared_classes() as $className) {
            if (!TextHandler::startsWith($className, ConfigStorage::$system->allowedNsPrefixes)) {
                continue;
            }

            try {
                $class = new ReflectionClass($className);
                if ($class->isInstantiable()) {
                    $classes[] = $class;
                }
            } catch (ReflectionException) {
            }
        }

        $caches = [];
        foreach ($this->processors as $processor) {
            $caches[$processor::class] = $processor->buildCache($classes);

            $processor->saveCache($caches[$processor::class]);
        }

        $metrics = ['modified' => time(), 'count' => count($this->getClassesInfo()), 'hash' => TextHandler::random()];

        if (!FileHandler::putVar($this->getMetricsFile(), $metrics)) {
            throw new RuntimeException(sprintf('Unable to write file %s', $this->getMetricsFile()));
        }

        return $caches;
    }

    /**
     * @return array<string, int>
     *
     * @throws RuntimeException
     */
    private function getClassesInfo(): array
    {
        static $classesInfo;
        if (isset($classesInfo)) {
            return $classesInfo;
        }

        $allowedNsRoots = [];
        foreach (ConfigStorage::$system->allowedNsPrefixes as $nsPrefix) {
            if (preg_match('/^[^\\\\]+\\\\/', $nsPrefix, $M)) {
                $allowedNsRoots[] = $M[0];
            } else {
                $allowedNsRoots[] = $nsPrefix;
            }
        }

        $classesInfo = [];
        foreach ($this->getLoader()->getPrefixesPsr4() as $namespace => $dirs) {
            if (!TextHandler::startsWith($namespace, $allowedNsRoots)) {
                continue;
            }

            foreach ($dirs as $dir) {
                /**
                 * @var RecursiveDirectoryIterator $info
                 */
                foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $info) {
                    if (!$info->isFile() || false === $info->getMTime() || !preg_match('/^[A-Z][A-Za-z\d]*\.php$/', $info->getFilename())) {
                        continue;
                    }

                    $className = $namespace . strtr(substr($info->getPathname(), strlen($dir) + 1, -4), DIRECTORY_SEPARATOR, '\\');

                    if (TextHandler::startsWith($className, ConfigStorage::$system->allowedNsPrefixes)) {
                        $classesInfo[$className] = $info->getMTime();
                    }
                }
            }
        }

        return $classesInfo;
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
