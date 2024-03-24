<?php declare(strict_types=1);

namespace SWF;

use RuntimeException;
use function count;
use function is_array;

abstract class AbstractRouter
{
    /** @var array<string,mixed> */
    protected static array $cache;

    protected string $appNs = 'App\\';

    /** @var string[] */
    private static array $classesFiles;

    private static bool $classesLoaded = false;

    /**
     * Reads and actualizes cache if needed.
     *
     * @throws RuntimeException
     */
    protected function readCache(string $cacheFile): void
    {
        $cache = @include $cacheFile;
        if (is_array($cache)) {
            static::$cache = $cache;
            if ('prod' === ConfigHolder::get()->env || $this->isCacheActual()) {
                return;
            }
        }

        $this->rebuildCache($this->initNewCache());
        $this->saveCache($cacheFile);
    }

    /**
     * @param array{time:int, count:int} $initialCache
     */
    abstract protected function rebuildCache(array $initialCache): void;

    /**
     * @return array{time:int, count:int}
     */
    private function initNewCache(): array
    {
        $this->loadAllClasses();

        return ['time' => time(), 'count' => count(self::$classesFiles)];
    }

    private function isCacheActual(): bool
    {
        $this->findAllClasses();
        if (count(self::$classesFiles) !== static::$cache['count']) {
            return false;
        }

        foreach (self::$classesFiles as $file) {
            if ((int) filemtime($file) > static::$cache['time']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws RuntimeException
     */
    private function saveCache(string $cacheFile): void
    {
        if (!FileHandler::putVar($cacheFile, static::$cache, LOCK_EX)) {
            throw new RuntimeException(sprintf('Unable to write file %s', $cacheFile));
        }
    }

    private function findAllClasses(): void
    {
        if (isset(self::$classesFiles)) {
            return;
        }

        self::$classesFiles = [];
        foreach (DirHandler::scan(sprintf('%s/src', APP_DIR), true, true) as $item) {
            if (is_file($item) && str_ends_with($item, '.php')) {
                self::$classesFiles[] = $item;
            }
        }
    }

    private function loadAllClasses(): void
    {
        if (self::$classesLoaded) {
            return;
        }

        $this->findAllClasses();
        foreach (self::$classesFiles as $file) {
            require_once $file;
        }

        self::$classesLoaded = true;
    }
}
