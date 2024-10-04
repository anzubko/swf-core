<?php declare(strict_types=1);

namespace SWF;

use SWF\Exception\ExitSimulationException;
use function count;

/**
 * @internal
 */
final class CommandUtil
{
    /**
     * @throws ExitSimulationException
     */
    public function listAll(): void
    {
        $commands = CommandStorage::$cache;
        if (count($commands) === 0) {
            i(CommandLineManager::class)->writeLn('No commands found')->exit();
        }

        i(CommandLineManager::class)->writeLn('Available commands:');

        ksort($commands);
        foreach ($commands as $name => $command) {
            i(CommandLineManager::class)->write(sprintf("\n%s --> %s\n", $name, $command['method']));

            if (isset($command['description'])) {
                i(CommandLineManager::class)->writeLn(sprintf('  %s', $command['description']));
            }
        }

        i(CommandLineManager::class)->writeLn();
    }
}
