<?php
declare(strict_types=1);

namespace SWF;

use Psr\EventDispatcher\StoppableEventInterface;

abstract class AbstractEvent implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    /**
     * @inheritDoc
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * Stops propagation of this event to other listeners.
     */
    public function stopPropagation(): static
    {
        $this->propagationStopped = true;

        return $this;
    }
}
