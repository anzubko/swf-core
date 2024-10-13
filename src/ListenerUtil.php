<?php
declare(strict_types=1);

namespace SWF;

use SWF\Exception\ExitSimulationException;
use function count;

/**
 * @internal
 */
final class ListenerUtil
{
    /**
     * @throws ExitSimulationException
     */
    public static function listAll(): void
    {
        $listenersByType = [];
        foreach (ListenerStorage::$cache as $listener) {
            $listenersByType[$listener['type']][] = $listener;
        }

        if (count($listenersByType) === 0) {
            CommandLineManager::writeLn('No listeners found');
            CommandLineManager::end();
        }

        CommandLineManager::writeLn('Registered listeners:');

        ksort($listenersByType);
        foreach ($listenersByType as $type => $listeners) {
            CommandLineManager::write(sprintf("\n%s -->\n", $type));

            usort($listeners, fn ($a, $b) => ($b['priority'] ?? 0.0) <=> ($a['priority'] ?? 0.0));

            foreach ($listeners as $listener) {
                if (($listener['priority'] ?? 0.0) === 0.0) {
                    CommandLineManager::writeLn(sprintf('  %s', $listener['callback']));
                } else {
                    CommandLineManager::writeLn(sprintf('  %s (priority %s)', $listener['callback'], $listener['priority']));
                }
            }
        }

        CommandLineManager::writeLn();
    }
}
