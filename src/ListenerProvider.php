<?php
declare(strict_types=1);

namespace SWF;

use ReflectionException;
use ReflectionFunction;
use SWF\Interface\ListenerProviderInterface;
use function in_array;

final class ListenerProvider implements ListenerProviderInterface
{
    /**
     * @inheritDoc
     */
    public function add(callable $callback, float $priority = 0.0, bool $disposable = false, bool $persistent = false): void
    {
        try {
            foreach (i(ListenerProcessor::class)->getTypes(new ReflectionFunction($callback(...))) as $typeName) {
                ListenerStorage::$cache[] = [
                    'callback' => $callback,
                    'type' => $typeName,
                    'priority' => $priority,
                    'disposable' => $disposable,
                    'persistent' => $persistent,
                ];
            }
        } catch (ReflectionException) {
        }
    }

    /**
     * @inheritDoc
     */
    public function removeByType(array|string $types, bool $force = false): void
    {
        foreach (ListenerStorage::$cache as $i => $listener) {
            if (!$force && ($listener['persistent'] ?? false) || !in_array($listener['type'], (array) $types, true)) {
                continue;
            }

            unset(ListenerStorage::$cache[$i]);
        }
    }

    /**
     * @inheritDoc
     */
    public function removeAll(bool $force = false): void
    {
        if ($force) {
            ListenerStorage::$cache = [];
        } else {
            foreach (ListenerStorage::$cache as $i => $listener) {
                if ($listener['persistent'] ?? false) {
                    continue;
                }

                unset(ListenerStorage::$cache[$i]);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getListenersForEvent(object $event, bool $removeDisposables = false): iterable
    {
        $listeners = [];
        foreach (ListenerStorage::$cache as $i => &$listener) {
            if (!$event instanceof $listener['type']) {
                continue;
            }

            $listener['normalizedCallback'] ??= CallbackHandler::normalize($listener['callback']);

            $listeners[] = $listener;

            if ($removeDisposables && ($listener['disposable'] ?? false)) {
                unset(ListenerStorage::$cache[$i]);
            }
        }

        usort($listeners, fn ($a, $b) => ($b['priority'] ?? 0.0) <=> ($a['priority'] ?? 0.0));

        return array_column($listeners, 'normalizedCallback');
    }
}
