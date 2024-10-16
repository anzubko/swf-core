<?php
declare(strict_types=1);

namespace SWF;

use Psr\EventDispatcher\StoppableEventInterface;
use Throwable;

final class EventConsumer
{
    /**
     * Provides all relevant consumers with an event to process.
     *
     * @template T of object
     *
     * @param T $event
     *
     * @return T
     *
     * @throws Throwable
     */
    public function consume(object $event)
    {
        foreach (i(ConsumerProvider::class)->getConsumersForEvent($event) as $consumer) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                return $event;
            }

            $consumer($event);
        }

        return $event;
    }
}
