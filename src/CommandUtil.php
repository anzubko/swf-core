<?php
declare(strict_types=1);

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
    public static function listAll(): void
    {
        $commands = CommandStorage::$cache;
        if (count($commands) === 0) {
            CommandLineManager::writeLn('No commands found');
            CommandLineManager::end();
        }

        CommandLineManager::writeLn('Available commands:');

        ksort($commands);
        foreach ($commands as $name => $command) {
            CommandLineManager::write(sprintf("\n%s --> %s\n", $name, $command['method']));

            if (isset($command['description'])) {
                CommandLineManager::writeLn(sprintf('  %s', $command['description']));
            }
        }

        CommandLineManager::writeLn();
    }
}
