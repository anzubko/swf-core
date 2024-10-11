<?php declare(strict_types=1);

namespace SWF;

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

    /**
     * @var array<class-string<AbstractActionProcessor>>
     */
    private array $processors = [
        CommandProcessor::class,
        ControllerProcessor::class,
        ListenerProcessor::class,
        RelationProcessor::class,
    ];

    /**
     * @var array<string, int>
     */
    private array $allowedClasses;

    /**
     * @throws RuntimeException
     */
    public function prepare(): void
    {
        foreach ($this->readOrRebuildCaches() as $class => $cache) {
            $this->getProcessor($class)->storageCache($cache);
        }
    }

    /**
     * @param class-string<AbstractActionProcessor> $class
     */
    private function getProcessor(string $class): AbstractActionProcessor
    {
        return i($class);
    }

    /**
     * @return array<class-string<AbstractActionProcessor>, mixed[]>
     *
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
        foreach ($this->processors as $class) {
            $cache = @include $this->getProcessor($class)->getCacheFile();
            if (!is_array($cache)) {
                return null;
            }

            $caches[$class] = $cache;
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
     */
    private function isOutdated(array $metrics): bool
    {
        if (count($this->getAllowedClasses()) !== $metrics['count']) {
            return true;
        }

        foreach ($this->getAllowedClasses() as $modified) {
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
     * @throws RuntimeException
     */
    private function rebuild(): array
    {
        foreach ($this->getAllowedClasses() as $class => $modified) {
            class_exists($class);
        }

        $rClasses = [];
        foreach (get_declared_classes() as $class) {
            if (!TextHandler::startsWith($class, ConfigStorage::$system->allowedNsPrefixes)) {
                continue;
            }

            try {
                $rClass = new ReflectionClass($class);
                if ($rClass->isInstantiable()) {
                    $rClasses[] = $rClass;
                }
            } catch (ReflectionException) {
            }
        }

        $caches = [];
        foreach ($this->processors as $class) {
            $caches[$class] = $this->getProcessor($class)->buildCache($rClasses);

            $this->getProcessor($class)->saveCache($caches[$class]);
        }

        $metrics = ['modified' => time(), 'count' => count($this->getAllowedClasses()), 'hash' => TextHandler::random()];

        if (!FileHandler::putVar($this->getMetricsFile(), $metrics)) {
            throw new RuntimeException(sprintf('Unable to write file %s', $this->getMetricsFile()));
        }

        return $caches;
    }

    /**
     * @return array<string, int>
     */
    private function getAllowedClasses(): array
    {
        return $this->allowedClasses ??= $this->findAllowedClasses();
    }

    /**
     * @return array<string, int>
     */
    private function findAllowedClasses(): array
    {
        $allowedNsRoots = [];
        foreach (ConfigStorage::$system->allowedNsPrefixes as $nsPrefix) {
            if (preg_match('/^[^\\\\]+\\\\/', $nsPrefix, $M)) {
                $allowedNsRoots[] = $M[0];
            } else {
                $allowedNsRoots[] = $nsPrefix;
            }
        }

        $allowedClasses = [];
        foreach (LoaderStorage::$loader->getPrefixesPsr4() as $namespace => $dirs) {
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

                    $class = $namespace . strtr(substr($info->getPathname(), strlen($dir) + 1, -4), DIRECTORY_SEPARATOR, '\\');

                    if (TextHandler::startsWith($class, ConfigStorage::$system->allowedNsPrefixes)) {
                        $allowedClasses[$class] = $info->getMTime();
                    }
                }
            }
        }

        return $allowedClasses;
    }
}
