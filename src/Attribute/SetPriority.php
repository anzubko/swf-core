<?php
declare(strict_types=1);

namespace SWF\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class SetPriority
{
    /**
     * Sets subclass priority.
     *
     * @param float $priority Subclass with higher priority will be earlier in returned list.
     */
    public function __construct(
        private float $priority,
    ) {
    }

    public function getPriority(): float
    {
        return $this->priority;
    }
}
