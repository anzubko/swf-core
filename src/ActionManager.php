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
use function strlen;

final class ActionManager
{
    private const RELATIVE_METRICS_FILE = '/.system/actions.metrics.php';

    private const LOCK_KEY = '.system/action.manager';

    private const NAMESPACE_SEPARATOR = '\\';

    private string $metricsFile;

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

    public function __construct()
    {
        $this->metricsFile = ConfigStorage::$system->cacheDir . self::RELATIVE_METRICS_FILE;

        $this->processors = [new CommandProcessor(), new ControllerProcessor(), new ListenerProcessor(), new RelationProcessor()];
    }

    /**
     * @param class-string<AbstractActionProcessor> $className
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    public function getCache(string $className): ?ActionCache
    {
        if (!isset($this->caches)) {
            $this->caches = [];
            $this->caches = $this->readOrRebuildCaches();
        }

        return $this->caches[$className] ?? null;
    }

    /**
     * @return array<class-string<AbstractActionProcessor>, ActionCache>
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    private function readOrRebuildCaches(): array
    {
        $caches = $this->readSavedCaches();
        if (null !== $caches) {
            if ('prod' === ConfigStorage::$system->env) {
                return $caches;
            }

            $metrics = $this->getMetrics();
            if (null !== $metrics && !$this->isOutdated($metrics)) {
                return $caches;
            }
        } else {
            $metrics = null;
        }

        i(FileLocker::class)->acquire(self::LOCK_KEY);

        try {
            $caches = $this->readSavedCaches();
            if (null === $caches || null === $this->getMetrics($metrics)) {
                $caches = $this->rebuild();
            }
        } finally {
            i(FileLocker::class)->release(self::LOCK_KEY);
        }

        return $caches;
    }

    /**
     * @return array<class-string<AbstractActionProcessor>, ActionCache>|null
     */
    private function readSavedCaches(): ?array
    {
        $caches = [];
        foreach ($this->processors as $processor) {
            $cache = @include $processor->getCacheFile();
            if (!is_array($cache)) {
                return null;
            }

            $caches[$processor::class] = new ActionCache($cache);
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

    /**
     * Method can generate not catchable fatal errors.
     *
     * @return array<class-string<AbstractActionProcessor>, ActionCache>
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    private function rebuild(): array
    {
        $this->classesInfo ??= $this->getClassesInfo();
        foreach ($this->classesInfo as $info) {
            class_exists($info['class']);
        }

        $classes = new ActionClasses();
        foreach (get_declared_classes() as $className) {
            if (!TextHandler::startsWith($className, ConfigStorage::$system->allowedNsPrefixes)) {
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

        if (!FileHandler::putVar($this->metricsFile, ['modified' => time(), 'count' => count($this->classesInfo), 'hash' => TextHandler::random()])) {
            throw new RuntimeException(sprintf('Unable to write file %s', $this->metricsFile));
        }

        return $caches;
    }

    /**
     * @return array<array{class:string, file:string, modified:int}>
     *
     * @throws RuntimeException
     */
    private function getClassesInfo(): array
    {
        $allowedNsRoots = [];
        foreach (ConfigStorage::$system->allowedNsPrefixes as $nsPrefix) {
            $chunks = explode(self::NAMESPACE_SEPARATOR, $nsPrefix, 2);
            $allowedNsRoots[] = match (true) {
                count($chunks) > 1 => $chunks[0] . self::NAMESPACE_SEPARATOR,
                default => $nsPrefix,
            };
        }

        $classesInfo = [];
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

                    $croppedFile = substr($info->getPathname(), strlen($dir) + 1, -4);
                    $className = $namespace . strtr($croppedFile, DIRECTORY_SEPARATOR, self::NAMESPACE_SEPARATOR);
                    if (!TextHandler::startsWith($className, ConfigStorage::$system->allowedNsPrefixes)) {
                        continue;
                    }

                    $classesInfo[] = ['class' => $className, 'file' => $info->getPathname(), 'modified' => $info->getMTime()];
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
