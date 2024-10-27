<?php
declare(strict_types=1);

namespace SWF;

use SWF\Interface\ConsumerProviderInterface;

final class ConsumerProvider implements ConsumerProviderInterface
{
    /**
     * @inheritDoc
     */
    public function getConsumersForMessage(object $message): iterable
    {
        $consumers = [];
        foreach (ConsumerStorage::$cache as &$consumer) {
            if (!$message instanceof $consumer['type']) {
                continue;
            }

            $consumer['normalizedCallback'] ??= CallbackHandler::normalize($consumer['callback']);

            $consumers[] = $consumer;
        }

        usort($consumers, fn ($a, $b) => ($b['priority'] ?? 0.0) <=> ($a['priority'] ?? 0.0));

        return array_column($consumers, 'normalizedCallback');
    }
}
