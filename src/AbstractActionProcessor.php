<?php declare(strict_types=1);

namespace SWF;

use ReflectionMethod;
use RuntimeException;

abstract class AbstractActionProcessor
{
    protected string $cacheFile;

    public function getCacheFile(): string
    {
        return $this->cacheFile;
    }

    abstract public function initializeCache(): ActionCache;

    abstract public function processMethod(ActionCache $cache, ReflectionMethod $method): void;

    abstract public function finalizeCache(ActionCache $cache): void;

    /**
     * @throws RuntimeException
     */
    public function saveCache(ActionCache $cache): void
    {
        if (!FileHandler::putVar($this->cacheFile, $cache->data, LOCK_EX)) {
            throw new RuntimeException(sprintf('Unable to write file %s', $this->cacheFile));
        }
    }
}
