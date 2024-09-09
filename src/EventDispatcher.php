<?php declare(strict_types=1);

namespace SWF;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\StoppableEventInterface;
use Throwable;

final class EventDispatcher implements EventDispatcherInterface
{
    private static self $instance;

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
    }

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
        foreach (ListenerProvider::getInstance()->getListenersForEvent($event) as $listener) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                return $event;
            }

            $listener($event);
        }

        return $event;
    }
}
