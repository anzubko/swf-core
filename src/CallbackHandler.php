<?php declare(strict_types=1);

namespace SWF;

use LogicException;
use function is_string;

final class CallbackHandler
{
    /**
     * Normalizes callback.
     *
     * @param callable|mixed[]|string $callback
     *
     * @throws LogicException
     */
    public static function normalize(callable|array|string $callback): callable
    {
        if (is_callable($callback)) {
            return $callback;
        }

        if (is_string($callback)) {
            $callback = explode('::', $callback);
        }

        if (is_string($callback[0]) && class_exists($callback[0])) {
            $callback[0] = instance($callback[0]);
            if (is_callable($callback)) {
                return $callback;
            }
        }

        throw new LogicException('Unable to normalize callback');
    }
}
