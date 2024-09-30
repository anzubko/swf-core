<?php declare(strict_types=1);

namespace SWF;

use InvalidArgumentException;
use LogicException;
use Psr\EventDispatcher\ListenerProviderInterface;
use ReflectionException;
use ReflectionFunction;
use RuntimeException;
use SWF\Exception\ExitSimulationException;
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
     * @param float $priority Listener with higher priority will be called earlier.
     * @param bool $disposable Listener can be called only once.
     * @param bool $persistent Listener can only be removed with the force parameter.
     *
     * @throws InvalidArgumentException
     * @throws LogicException
     */
    public function addListener(callable $callback, float $priority = 0.0, bool $disposable = false, bool $persistent = false): void
    {
        if (null === $this->cache) {
            return;
        }

        foreach (ListenerProcessor::getTypes(new ReflectionFunction($callback(...))) as $typeName) {
            $this->cache->data['listeners'][] = [
                'callback' => $callback,
                'type' => $typeName,
                'priority' => $priority,
                'disposable' => $disposable,
                'persistent' => $persistent,
            ];
        }
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
        } else {
            foreach ($this->cache->data['listeners'] as $i => $listener) {
                if ($listener['persistent'] ?? false) {
                    continue;
                }

                unset($this->cache->data['listeners'][$i]);
            }
        }
    }

    /**
     * @inheritDoc
     *
     * @return iterable<callable>
     *
     * @throws ReflectionException
     */
    public function getListenersForEvent(object $event, bool $removeDisposables = false): iterable
    {
        if (null === $this->cache) {
            return [];
        }

        $listeners = [];
        foreach ($this->cache->data['listeners'] as $i => &$listener) {
            if (!$event instanceof $listener['type']) {
                continue;
            }

            $listener['normalizedCallback'] ??= CallbackHandler::normalize($listener['callback']);

            $listeners[] = $listener;

            if ($removeDisposables && ($listener['disposable'] ?? false)) {
                unset($this->cache->data['listeners'][$i]);
            }
        }

        return array_column($this->sort($listeners), 'normalizedCallback');
    }

    /**
     * @throws ExitSimulationException
     *
     * @internal
     */
    public function listAll(): void
    {
        if (null === $this->cache) {
            return;
        }

        $listenersByType = [];
        foreach ($this->cache->data['listeners'] as $listener) {
            $listenersByType[$listener['type']][] = $listener;
        }

        if (count($listenersByType) === 0) {
            i(CommandLineManager::class)->writeLn('No listeners found')->exit();
        }

        i(CommandLineManager::class)->writeLn('Registered listeners:');

        ksort($listenersByType);
        foreach ($listenersByType as $type => $listeners) {
            i(CommandLineManager::class)->write(sprintf("\n%s -->\n", $type));

            foreach ($this->sort($listeners) as $listener) {
                if (($listener['priority'] ?? 0.0) === 0.0) {
                    i(CommandLineManager::class)->writeLn(sprintf('  %s', $listener['callback']));
                } else {
                    i(CommandLineManager::class)->writeLn(sprintf('  %s (priority %s)', $listener['callback'], $listener['priority']));
                }
            }
        }

        i(CommandLineManager::class)->writeLn();
    }

    /**
     * @param mixed[] $listeners
     *
     * @return mixed[]
     */
    private function sort(array $listeners): array
    {
        usort($listeners, fn ($a, $b) => ($b['priority'] ?? 0.0) <=> ($a['priority'] ?? 0.0));

        return $listeners;
    }
}
