<?php
declare(strict_types=1);

namespace SWF\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
readonly class AsConsumer
{
    /**
     * Registers consumer.
     *
     * @param float $priority Consumer with higher priority will be called earlier.
     */
    public function __construct(
        private float $priority = 0.0,
    ) {
    }

    public function getPriority(): float
    {
        return $this->priority;
    }
}
