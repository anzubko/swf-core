<?php declare(strict_types=1);

namespace SWF;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\StoppableEventInterface;
use ReflectionException;

final class EventDispatcher implements EventDispatcherInterface
{
    /**
     * @inheritDoc
     *
     * @template T of object
     *
     * @param T $event
     *
     * @return T
     *
     * @throws ReflectionException
     */
    public function dispatch(object $event)
    {
        foreach (i(ListenerProvider::class)->getListenersForEvent($event, true) as $listener) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                return $event;
            }

            $listener($event);
        }

        return $event;
    }
}
