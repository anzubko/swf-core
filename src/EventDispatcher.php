<?php declare(strict_types=1);

namespace SWF;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\StoppableEventInterface;
use Psr\Log\LogLevel;
use Throwable;

final class EventDispatcher implements EventDispatcherInterface
{
    private static self $instance;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
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
    public function dispatch(object $event, bool $silent = false)
    {
        foreach (ListenerProvider::getInstance()->getListenersForEvent($event) as $listener) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                return $event;
            }

            if ($silent) {
                try {
                    $listener($event);
                } catch (Throwable $e) {
                    CommonLogger::getInstance()->log(LogLevel::ERROR, $e);
                }
            } else {
                $listener($event);
            }
        }

        return $event;
    }
}
