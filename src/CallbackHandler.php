<?php declare(strict_types=1);

namespace SWF;

use LogicException;
use Throwable;
use function is_string;

final class CallbackHandler
{
    /**
     * Normalizes callback.
     *
     * @param callable|array{object|string, string}|string $callback
     *
     * @throws Throwable
     */
    public static function normalize(callable|array|string $callback): callable
    {
        if (is_callable($callback)) {
            return $callback;
        }

        if (is_string($callback)) {
            $callback = explode('::', $callback);
        }

        static $instances = [];

        $callback[0] = $instances[$callback[0]] ??= new $callback[0];
        if (is_callable($callback)) {
            return $callback;
        }

        throw new LogicException('Unable to normalize callback');
    }
}
