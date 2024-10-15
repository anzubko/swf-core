<?php
declare(strict_types=1);

namespace SWF;

use SWF\Exception\ExitSimulationException;
use function count;

/**
 * @internal
 */
final class ConsumerUtil
{
    /**
     * @throws ExitSimulationException
     */
    public static function listAll(): void
    {
        $consumersByType = [];
        foreach (ConsumerStorage::$cache as $consumer) {
            $consumersByType[$consumer['type']][] = $consumer;
        }

        if (count($consumersByType) === 0) {
            CommandLineManager::writeLn('No consumers found');
            CommandLineManager::end();
        }

        CommandLineManager::writeLn('Registered consumers:');

        ksort($consumersByType);
        foreach ($consumersByType as $type => $consumers) {
            CommandLineManager::write(sprintf("\n%s -->\n", $type));

            usort($consumers, fn ($a, $b) => ($b['priority'] ?? 0.0) <=> ($a['priority'] ?? 0.0));

            foreach ($consumers as $consumer) {
                if (($consumer['priority'] ?? 0.0) !== 0.0) {
                    CommandLineManager::writeLn(sprintf('  %s (priority %s)', $consumer['callback'], $consumer['priority']));
                } else {
                    CommandLineManager::writeLn(sprintf('  %s', $consumer['callback']));
                }
            }
        }

        CommandLineManager::writeLn();
    }
}
