<?php
declare(strict_types=1);

namespace SWF;

use SWF\Enum\ActionTypeEnum;
use function is_string;

/**
 * @internal
 */
final class ControllerProvider
{
    public function getCurrentAction(): CurrentAction
    {
        $path = explode('?', $_SERVER['REQUEST_URI'], 2)[0];

        $actions = ControllerStorage::$cache['static'][$path] ?? null;
        if ($actions === null) {
            if (empty(ControllerStorage::$cache)) {
                return new CurrentAction(ActionTypeEnum::CONTROLLER);
            }

            if (preg_match(ControllerStorage::$cache['regex'], $path, $M)) {
                [$actions, $keys] = ControllerStorage::$cache['dynamic'][$M['MARK']];

                foreach ($keys as $i => $key) {
                    $_GET[$key] = $_REQUEST[$key] = $M[$i + 1];
                }
            }
        }

        if ($actions === null) {
            return new CurrentAction(ActionTypeEnum::CONTROLLER);
        }

        $action = $actions[$_SERVER['REQUEST_METHOD']] ?? $actions['ANY'] ?? null;
        if ($action === null) {
            return new CurrentAction(ActionTypeEnum::CONTROLLER);
        }

        if (is_string($action)) {
            $action = [$action, null];
        }

        return new CurrentAction(ActionTypeEnum::CONTROLLER, ...$action);
    }
}
