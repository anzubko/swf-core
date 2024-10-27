<?php
declare(strict_types=1);

namespace SWF\Interface;

interface StoppableMessageInterface
{
    /**
     * Is propagation stopped?
     *
     * This will typically only be used by the Dispatcher to determine if the previous consumer halted propagation.
     */
    public function isPropagationStopped() : bool;

    /**
     * Stops propagation of this message to other consumers.
     */
    public function stopPropagation(): static;
}
