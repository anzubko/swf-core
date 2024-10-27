<?php
declare(strict_types=1);

namespace SWF\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
readonly class AsListener
{
    /**
     * Registers listener.
     *
     * @param float $priority Listener with higher priority will be called earlier.
     * @param bool $disposable Listener can be called only once.
     * @param bool $persistent Listener can only be removed with the force parameter.
     */
    public function __construct(
        private float $priority = 0.0,
        private bool $disposable = false,
        private bool $persistent = false,
    ) {
    }

    public function getPriority(): float
    {
        return $this->priority;
    }

    public function isDisposable(): bool
    {
        return $this->disposable;
    }

    public function isPersistent(): bool
    {
        return $this->persistent;
    }
}
