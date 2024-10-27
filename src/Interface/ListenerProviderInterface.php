<?php
declare(strict_types=1);

namespace SWF\Interface;

use InvalidArgumentException;
use LogicException;
use Psr\EventDispatcher\ListenerProviderInterface as PsrListenerProviderInterface;
use ReflectionException;

interface ListenerProviderInterface extends PsrListenerProviderInterface
{
    /**
     * Adds listener.
     *
     * @param float $priority Listener with higher priority will be called earlier.
     * @param bool $disposable Listener can be called only once.
     * @param bool $persistent Listener can only be removed with the force parameter.
     *
     * @throws InvalidArgumentException
     * @throws LogicException
     */
    public function add(callable $callback, float $priority = 0.0, bool $disposable = false, bool $persistent = false): void;

    /**
     * Removes listeners by event type.
     *
     * @param string|string[] $types
     */
    public function removeByType(array|string $types, bool $force = false): void;

    /**
     * Removes all listeners.
     */
    public function removeAll(bool $force = false): void;

    /**
     * Returns listeners that are applicable to that event.
     *
     * @return iterable<callable>
     *
     * @throws ReflectionException
     */
    public function getListenersForEvent(object $event) : iterable;
}
