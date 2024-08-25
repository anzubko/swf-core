<?php declare(strict_types=1);

namespace SWF;

use LogicException;
use RuntimeException;

abstract class AbstractActionProcessor
{
    protected string $cacheFile;

    public function getCacheFile(): string
    {
        return $this->cacheFile;
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
        if (!FileHandler::putVar($this->cacheFile, $cache->data)) {
            throw new RuntimeException(sprintf('Unable to write file %s', $this->cacheFile));
        }
    }
}
