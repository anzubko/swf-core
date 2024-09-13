<?php declare(strict_types=1);

namespace SWF;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\StoppableEventInterface;
use Throwable;

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
     * @throws Throwable
     */
    public function dispatch(object $event)
    {
        foreach (i(ListenerProvider::class)->getListenersForEvent($event) as $listener) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                return $event;
            }

            $listener($event);
        }

        return $event;
    }
}
