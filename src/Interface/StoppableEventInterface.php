<?php
declare(strict_types=1);

namespace SWF\Interface;

use Psr\EventDispatcher\StoppableEventInterface as PsrStoppableEventInterface;

interface StoppableEventInterface extends PsrStoppableEventInterface
{
    /**
     * Is propagation stopped?
     *
     * This will typically only be used by the Dispatcher to determine if the previous listener halted propagation.
     */
    public function isPropagationStopped() : bool;

    /**
     * Stops propagation of this event to other listeners.
     */
    public function stopPropagation(): static;
}
