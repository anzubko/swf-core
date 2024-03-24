<?php declare(strict_types=1);

namespace SWF;

use InvalidArgumentException;
use Psr\EventDispatcher\ListenerProviderInterface;
use SWF\Router\ListenerRouter;
use Throwable;

final class ListenerProvider implements ListenerProviderInterface
{
    private static self $instance;

    protected ListenerRouter $listenerRouter;

    private function __construct()
    {
        $this->listenerRouter = ListenerRouter::getInstance();
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Adds listener.
     *
     * @param bool $disposable Listener can be called only once.
     * @param bool $persistent Listener can only be removed with the force parameter.
     *
     * @throws InvalidArgumentException
     */
    public function addListener(callable $callback, bool $disposable = false, bool $persistent = false): void
    {
        $this->listenerRouter->add($callback, $disposable, $persistent);
    }

    /**
     * Removes listeners by event type.
     *
     * @param string|string[] $type
     */
    public function removeListenersByType(array|string $type, bool $force = false): void
    {
        $this->listenerRouter->removeByType($type, $force);
    }

    /**
     * Removes all listeners.
     */
    public function removeAllListeners(bool $force = false): void
    {
        $this->listenerRouter->removeAll($force);
    }

    /**
     * @inheritDoc
     *
     * @return callable[]
     *
     * @throws Throwable
     */
    public function getListenersForEvent(object $event): iterable
    {
        yield from $this->listenerRouter->getForEvent($event);
    }
}
