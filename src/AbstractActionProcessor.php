<?php declare(strict_types=1);

namespace SWF;

use LogicException;
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
     * @throws LogicException
     */
    abstract public function buildCache(ActionClasses $classes): ActionCache;

    /**
     * @throws RuntimeException
     */
    public function saveCache(ActionCache $cache): void
    {
        if (!FileHandler::putVar($this->getCacheFile(), $cache->data)) {
            throw new RuntimeException(sprintf('Unable to write file %s', $this->getCacheFile()));
        }
    }
}
