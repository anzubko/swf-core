<?php declare(strict_types=1);

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
    public function listAll(): void
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
            i(CommandLineManager::class)->writeLn('No controllers found')->end();
        }

        i(CommandLineManager::class)->writeLn('Available controllers:');

        ksort($controllers);
        foreach ($controllers as $path => $controller) {
            i(CommandLineManager::class)->write(sprintf("\n%s %s --> %s\n", implode('|', $controller['methods']), $path, $controller['action'][0]));

            if (isset($controller['action'][1])) {
                i(CommandLineManager::class)->writeLn(sprintf('  alias: %s', $controller['action'][1]));
            }
        }

        i(CommandLineManager::class)->writeLn();
    }
}
