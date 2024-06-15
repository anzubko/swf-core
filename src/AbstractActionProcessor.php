<?php declare(strict_types=1);

namespace SWF;

use RuntimeException;

abstract class AbstractActionProcessor
{
    protected string $cachePath;

    public function getCachePath(): string
    {
        return $this->cachePath;
    }

    abstract public function buildCache(ActionClasses $classes): ActionCache;

    /**
     * @throws RuntimeException
     */
    public function saveCache(ActionCache $cache): void
    {
        if (!FileHandler::putVar($this->cachePath, $cache->data)) {
            throw new RuntimeException(sprintf('Unable to write file %s', $this->cachePath));
        }
    }
}
