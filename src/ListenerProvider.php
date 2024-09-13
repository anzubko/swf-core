<?php declare(strict_types=1);

namespace SWF;

use InvalidArgumentException;
use LogicException;
use Psr\EventDispatcher\ListenerProviderInterface;
use ReflectionFunction;
use RuntimeException;
use function count;
use function in_array;

final class ListenerProvider implements ListenerProviderInterface
{
    private ?ActionCache $cache;

    /**
     * @throws LogicException
     * @throws RuntimeException
     */
    public function __construct()
    {
        $this->cache = i(ActionManager::class)->getCache(ListenerProcessor::class);
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
        if (null === $this->cache) {
            return;
        }

        $params = (new ReflectionFunction($callback(...)))->getParameters();
        $type = count($params) === 0 ? null : $params[0]->getType();
        if (null === $type) {
            throw new InvalidArgumentException('Listener must have first parameter with declared type');
        }

        $listener = [
            'callback' => $callback,
            'type' => (string) $type,
        ];

        if ($disposable) {
            $listener['disposable'] = true;
        }

        if ($persistent) {
            $listener['persistent'] = true;
        }

        $this->cache->data['listeners'][] = $listener;
    }

    /**
     * Removes listeners by event type.
     *
     * @param string|string[] $types
     */
    public function removeListenersByType(array|string $types, bool $force = false): void
    {
        if (null === $this->cache) {
            return;
        }

        foreach ($this->cache->data['listeners'] as $i => $listener) {
            if (!$force && ($listener['persistent'] ?? false) || !in_array($listener['type'], (array) $types, true)) {
                continue;
            }

            unset($this->cache->data['listeners'][$i]);
        }
    }

    /**
     * Removes all listeners.
     */
    public function removeAllListeners(bool $force = false): void
    {
        if (null === $this->cache) {
            return;
        }

        if ($force) {
            $this->cache->data['listeners'] = [];
            return;
        }

        foreach ($this->cache->data['listeners'] as $i => $listener) {
            if ($listener['persistent'] ?? false) {
                continue;
            }

            unset($this->cache->data['listeners'][$i]);
        }
    }

    /**
     * @inheritDoc
     *
     * @return iterable<callable>
     *
     * @throws LogicException
     */
    public function getListenersForEvent(object $event): iterable
    {
        if (null === $this->cache) {
            return;
        }

        foreach ($this->cache->data['listeners'] as $i => &$listener) {
            if (!$event instanceof $listener['type']) {
                continue;
            }

            $listener['callback'] = CallbackHandler::normalize($listener['callback']);

            if ($listener['disposable'] ?? false) {
                unset($this->cache->data['listeners'][$i]);
            }

            yield $listener['callback'];
        }
    }

    public function showAll(): void
    {
        if (null === $this->cache) {
            return;
        }

        $listeners = [];
        foreach ($this->cache->data['listeners'] as $listener) {
            $listeners[$listener['type']][] = $listener['callback'];
        }

        if (count($listeners) === 0) {
            echo "No listeners found.\n";
            return;
        }

        echo "Registered listeners:\n";

        ksort($listeners);
        foreach ($listeners as $type => $actions) {
            echo sprintf("\n%s -->\n", $type);

            sort($actions);
            foreach ($actions as $action) {
                echo sprintf("  %s\n", $action);
            }
        }

        echo "\n";
    }
}
