<?php
declare(strict_types=1);

namespace SWF\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
readonly class AsConsumer
{
    /**
     * Registers consumer.
     *
     * @param float $priority Consumer with higher priority will be called earlier.
     */
    public function __construct(
        public float $priority = 0.0,
    ) {
    }
}
