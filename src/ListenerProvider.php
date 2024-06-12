<?php declare(strict_types=1);

namespace SWF;

use InvalidArgumentException;
use LogicException;
use Psr\EventDispatcher\ListenerProviderInterface;
use ReflectionFunction;
use RuntimeException;
use Throwable;
use function count;
use function in_array;

final class ListenerProvider implements ListenerProviderInterface
{
    private static ActionCache $cache;

    private static self $instance;

    /**
     * @throws LogicException
     * @throws RuntimeException
     */
    private function __construct()
    {
        self::$cache = ActionManager::getInstance()->getCache(ListenerProcessor::class);
    }

    /**
     * @throws LogicException
     * @throws RuntimeException
     */
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
        $params = (new ReflectionFunction($callback(...)))->getParameters();
        $type = count($params) === 0 ? null : $params[0]->getType();
        if (null === $type) {
            throw new InvalidArgumentException('Listener must have first parameter with declared type');
        }

        self::$cache->data['listeners'][] = [
            'callback' => $callback,
            'type' => (string) $type,
            'disposable' => $disposable,
            'persistent' => $persistent,
        ];
    }

    /**
     * Removes listeners by event type.
     *
     * @param string|string[] $types
     */
    public function removeListenersByType(array|string $types, bool $force = false): void
    {
        foreach (self::$cache->data['listeners'] as $i => $listener) {
            if (($force || !$listener['persistent']) && in_array($listener['type'], (array) $types, true)) {
                unset(self::$cache->data['listeners'][$i]);
            }
        }
    }

    /**
     * Removes all listeners.
     */
    public function removeAllListeners(bool $force = false): void
    {
        if ($force) {
            self::$cache->data['listeners'] = [];
        } else {
            foreach (self::$cache->data['listeners'] as $i => $listener) {
                if (!$listener['persistent']) {
                    unset(self::$cache->data['listeners'][$i]);
                }
            }
        }
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
        foreach (self::$cache->data['listeners'] as $i => &$listener) {
            if (!$event instanceof $listener['type']) {
                continue;
            }

            $listener['callback'] = CallbackHandler::normalize($listener['callback']);

            if ($listener['disposable']) {
                unset(self::$cache->data['listeners'][$i]);
            }

            yield $listener['callback'];
        }
    }
}
