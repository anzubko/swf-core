<?php
declare(strict_types=1);

namespace SWF;

use LogicException;
use function count;

final class ControllerRouter
{
    /**
     * Generates URL by action and optional parameters.
     *
     * @throws LogicException
     */
    public static function genUrl(string $action, string|int|float|null ...$params): string
    {
        if (empty(ControllerStorage::$cache)) {
            return '/';
        }

        $parametrizedAction = sprintf('%s:%s', $action, count($params));

        $index = ControllerStorage::$cache['actions'][$parametrizedAction] ?? null;
        if ($index === null) {
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
            if ($value !== null) {
                $url[(int) $i * 2 + 1] = $value;
            }
        }

        return implode($url);
    }
}
