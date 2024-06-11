<?php declare(strict_types=1);

namespace SWF;

use Composer\Autoload\ClassLoader;
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
    private array $allowedNss;

    /**
     * @var array<string, int>
     */
    private array $classesFiles;

    private static self $instance;

    /**
     * @throws RuntimeException
     */
    private function __construct()
    {
        $this->allowedNss = config('system')->get('namespaces');

        $this->processors = [
            new CommandProcessor(),
            new ControllerProcessor(),
            new ListenerProcessor(),
        ];

        if ('prod' === config('system')->get('env')) {
            if ($this->readCaches()) {
                return;
            }
        } elseif ($this->compareMetrics()) {
            if ($this->readCaches()) {
                return;
            }
        }

        $this->rebuild();
    }

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
    private function compareMetrics(): bool
    {
        $metrics = @include $this->metricsFile;
        if (!is_array($metrics)) {
            return false;
        }

        $this->findClassesFiles();

        if (count($this->classesFiles) !== $metrics['count']) {
            return false;
        }

        foreach ($this->classesFiles as $mTime) {
            if ($mTime > $metrics['time']) {
                return false;
            }
        }

        return true;
    }

    private function readCaches(): bool
    {
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
        $this->findClassesFiles();

        foreach ($this->classesFiles as $file => $mTime) {
            require_once $file;
        }

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

        $metric = ['time' => time(), 'count' => count($this->classesFiles)];

        if (!FileHandler::putVar($this->metricsFile, $metric, LOCK_EX)) {
            throw new RuntimeException(sprintf('Unable to write file %s', $this->metricsFile));
        }
    }

    /**
     * @throws RuntimeException
     */
    private function findClassesFiles(): void
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
                    if ($info->isFile() && false !== $info->getMTime() && 'php' === $info->getExtension()) {
                        $this->classesFiles[$info->getPathname()] = $info->getMTime();
                    }
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
            if (str_starts_with($className, 'ComposerAutoloaderInit')) {
                $loaderGetter = sprintf('%s::getLoader', $className);
                if (is_callable($loaderGetter)) {
                    return $loaderGetter();
                }
            }
        }

        throw new RuntimeException('Unable to find composer loader');
    }

    private function isNsAllowed(string $ns): bool
    {
        foreach ($this->allowedNss as $allowedNs) {
            if (str_starts_with($ns, $allowedNs)) {
                return true;
            }
        }

        return false;
    }
}
