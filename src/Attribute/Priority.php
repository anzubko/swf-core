<?php declare(strict_types=1);

namespace SWF\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class Priority
{
    /**
     * Sets class priority for children() function.
     *
     * @param float $priority Class with higher priority will be earlier in returned list.
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
