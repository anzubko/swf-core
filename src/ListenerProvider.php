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
    private static ?ActionCache $cache;

    private static self $instance;

    /**
     * @throws LogicException
     * @throws RuntimeException
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * @throws LogicException
     * @throws RuntimeException
     */
    private function __construct()
    {
        self::$cache = ActionManager::getInstance()->getCache(ListenerProcessor::class);
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
        if (!isset(self::$cache)) {
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

        self::$cache->data['listeners'][] = $listener;
    }

    /**
     * Removes listeners by event type.
     *
     * @param string|string[] $types
     */
    public function removeListenersByType(array|string $types, bool $force = false): void
    {
        if (!isset(self::$cache)) {
            return;
        }

        foreach (self::$cache->data['listeners'] as $i => $listener) {
            if (!$force && ($listener['persistent'] ?? false) || !in_array($listener['type'], (array) $types, true)) {
                continue;
            }

            unset(self::$cache->data['listeners'][$i]);
        }
    }

    /**
     * Removes all listeners.
     */
    public function removeAllListeners(bool $force = false): void
    {
        if (!isset(self::$cache)) {
            return;
        }

        if ($force) {
            self::$cache->data['listeners'] = [];
            return;
        }

        foreach (self::$cache->data['listeners'] as $i => $listener) {
            if ($listener['persistent'] ?? false) {
                continue;
            }

            unset(self::$cache->data['listeners'][$i]);
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
        if (!isset(self::$cache)) {
            return;
        }

        foreach (self::$cache->data['listeners'] as $i => &$listener) {
            if (!$event instanceof $listener['type']) {
                continue;
            }

            $listener['callback'] = CallbackHandler::normalize($listener['callback']);

            if ($listener['disposable'] ?? false) {
                unset(self::$cache->data['listeners'][$i]);
            }

            yield $listener['callback'];
        }
    }

    public function showAll(): never
    {
        if (!isset(self::$cache)) {
            exit(1);
        }

        $listeners = [];
        foreach (self::$cache->data['listeners'] as $listener) {
            $listeners[$listener['type']][] = $listener['callback'];
        }

        if (count($listeners) === 0) {
            echo "No listeners found.\n";
            exit(0);
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
        exit(0);
    }
}
