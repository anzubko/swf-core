<?php declare(strict_types=1);

namespace SWF;

use App\Config\SystemConfig;
use LogicException;
use RuntimeException;

abstract class AbstractActionProcessor
{
    protected string $relativeCacheFile;

    public function getCacheFile(): string
    {
        return i(SystemConfig::class)->cacheDir . $this->relativeCacheFile;
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
