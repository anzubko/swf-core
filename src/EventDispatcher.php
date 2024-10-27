<?php
declare(strict_types=1);

namespace SWF;

use SWF\Interface\EventDispatcherInterface;
use SWF\Interface\StoppableEventInterface;

final class EventDispatcher implements EventDispatcherInterface
{
    /**
     * @inheritDoc
     */
    public function dispatch(mixed $event)
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
