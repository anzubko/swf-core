<?php
declare(strict_types=1);

namespace SWF\Interface;

use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Throwable;

interface EventDispatcherInterface extends PsrEventDispatcherInterface
{
    /**
     * Provides all relevant listeners with an event to process.
     *
     * @template T of object
     *
     * @param T $event
     *
     * @return T
     *
     * @throws Throwable
     */
    public function dispatch(mixed $event);
}
