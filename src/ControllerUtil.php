<?php
declare(strict_types=1);

namespace SWF;

use SWF\Exception\ExitSimulationException;
use function count;

/**
 * @internal
 */
final class ControllerUtil
{
    /**
     * @throws ExitSimulationException
     */
    public static function listAll(): void
    {
        if (empty(ControllerStorage::$cache)) {
            return;
        }

        $controllers = [];
        foreach (ControllerStorage::$cache['static'] as $path => $actions) {
            foreach ($actions as $method => $action) {
                $action = (array) $action;
                $controllers[$path]['methods'][] = $method;
                $controllers[$path]['action'] = $action;
            }
        }

        foreach (ControllerStorage::$cache['dynamic'] as $actions) {
            foreach ($actions[0] as $method => $action) {
                $action = (array) $action;
                $parametrizedAction = sprintf('%s:%d', ...$action);
                $urlIndex = ControllerStorage::$cache['actions'][$parametrizedAction];
                $path = implode(ControllerStorage::$cache['urls'][$urlIndex]);
                $controllers[$path]['methods'][] = $method;
                $controllers[$path]['action'] = $action;
            }
        }

        if (count($controllers) === 0) {
            CommandLineManager::writeLn('No controllers found');
            CommandLineManager::end();
        }

        CommandLineManager::writeLn('Available controllers:');

        ksort($controllers);
        foreach ($controllers as $path => $controller) {
            CommandLineManager::write(sprintf("\n%s %s --> %s\n", implode('|', $controller['methods']), $path, $controller['action'][0]));

            if (isset($controller['action'][1])) {
                CommandLineManager::writeLn(sprintf('  alias: %s', $controller['action'][1]));
            }
        }

        CommandLineManager::writeLn();
    }
}
