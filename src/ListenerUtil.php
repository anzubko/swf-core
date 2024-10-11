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
    public function listAll(): void
    {
        $listenersByType = [];
        foreach (ListenerStorage::$cache as $listener) {
            $listenersByType[$listener['type']][] = $listener;
        }

        if (count($listenersByType) === 0) {
            i(CommandLineManager::class)->writeLn('No listeners found')->end();
        }

        i(CommandLineManager::class)->writeLn('Registered listeners:');

        ksort($listenersByType);
        foreach ($listenersByType as $type => $listeners) {
            i(CommandLineManager::class)->write(sprintf("\n%s -->\n", $type));

            usort($listeners, fn ($a, $b) => ($b['priority'] ?? 0.0) <=> ($a['priority'] ?? 0.0));

            foreach ($listeners as $listener) {
                if (($listener['priority'] ?? 0.0) === 0.0) {
                    i(CommandLineManager::class)->writeLn(sprintf('  %s', $listener['callback']));
                } else {
                    i(CommandLineManager::class)->writeLn(sprintf('  %s (priority %s)', $listener['callback'], $listener['priority']));
                }
            }
        }

        i(CommandLineManager::class)->writeLn();
    }
}
