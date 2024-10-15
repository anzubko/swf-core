<?php
declare(strict_types=1);

namespace SWF;

use ReflectionException;

final class ConsumerProvider
{
    /**
     * Returns consumers that are applicable to that event.
     *
     * @return iterable<callable>
     *
     * @throws ReflectionException
     */
    public function getConsumersForEvent(object $event): iterable
    {
        $consumers = [];
        foreach (ConsumerStorage::$cache as &$consumer) {
            if (!$event instanceof $consumer['type']) {
                continue;
            }

            $consumer['normalizedCallback'] ??= CallbackHandler::normalize($consumer['callback']);

            $consumers[] = $consumer;
        }

        usort($consumers, fn ($a, $b) => ($b['priority'] ?? 0.0) <=> ($a['priority'] ?? 0.0));

        return array_column($consumers, 'normalizedCallback');
    }
}
