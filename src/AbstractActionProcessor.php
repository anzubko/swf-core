<?php
declare(strict_types=1);

namespace SWF;

use LogicException;
use ReflectionClass;
use RuntimeException;

/**
 * @internal
 */
abstract class AbstractActionProcessor
{
    abstract protected function getRelativeCacheFile(): string;

    public function getCacheFile(): string
    {
        return ConfigStorage::$system->cacheDir . $this->getRelativeCacheFile();
    }

    /**
     * @param array<ReflectionClass<object>> $rClasses
     *
     * @return mixed[]
     *
     * @throws LogicException
     */
    abstract public function buildCache(array $rClasses): array;

    /**
     * @param mixed[] $cache
     */
    abstract public function storageCache(array $cache): void;

    /**
     * @param mixed[] $cache
     *
     * @throws RuntimeException
     */
    public function saveCache(array $cache): void
    {
        if (!FileHandler::putVar($this->getCacheFile(), $cache)) {
            throw new RuntimeException(sprintf('Unable to write file %s', $this->getCacheFile()));
        }
    }
}
