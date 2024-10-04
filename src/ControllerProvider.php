<?php declare(strict_types=1);

namespace SWF;

use LogicException;
use SWF\Enum\ActionTypeEnum;
use function count;
use function is_string;

final class ControllerProvider
{
    /**
     * Gets current action.
     */
    public function getCurrentAction(): CurrentActionInfo
    {
        $path = explode('?', $_SERVER['REQUEST_URI'], 2)[0];

        $actions = ControllerStorage::$cache['static'][$path] ?? null;
        if (null === $actions) {
            if (empty(ControllerStorage::$cache)) {
                return new CurrentActionInfo(ActionTypeEnum::CONTROLLER);
            }

            if (preg_match(ControllerStorage::$cache['regex'], $path, $M)) {
                [$actions, $keys] = ControllerStorage::$cache['dynamic'][$M['MARK']];

                foreach ($keys as $i => $key) {
                    $_GET[$key] = $_REQUEST[$key] = $M[$i + 1];
                }
            }
        }

        if (null === $actions) {
            return new CurrentActionInfo(ActionTypeEnum::CONTROLLER);
        }

        $action = $actions[$_SERVER['REQUEST_METHOD']] ?? $actions['ANY'] ?? null;
        if (null === $action) {
            return new CurrentActionInfo(ActionTypeEnum::CONTROLLER);
        }

        if (is_string($action)) {
            $action = [$action, null];
        }

        return new CurrentActionInfo(ActionTypeEnum::CONTROLLER, ...$action);
    }

    /**
     * Generates URL by action and optional parameters.
     *
     * @throws LogicException
     */
    public function genUrl(string $action, string|int|float|null ...$params): string
    {
        if (empty(ControllerStorage::$cache)) {
            return '/';
        }

        $parametrizedAction = sprintf('%s:%s', $action, count($params));

        $index = ControllerStorage::$cache['actions'][$parametrizedAction] ?? null;
        if (null === $index) {
            if (count($params) === 0) {
                throw new LogicException(sprintf('Unable to make URL by action %s', $action));
            }

            throw new LogicException(sprintf('Unable to make URL by action %s and %s parameter%s', $action, count($params), count($params) > 1 ? 's' : ''));
        }

        $url = ControllerStorage::$cache['urls'][$index];
        if (count($params) === 0) {
            return $url;
        }

        foreach ($params as $i => $value) {
            if (null !== $value) {
                $url[(int) $i * 2 + 1] = $value;
            }
        }

        return implode($url);
    }
}
