<?php declare(strict_types=1);

namespace SWF;

use ReflectionException;
use ReflectionMethod;
use function is_string;

final class CallbackHandler
{
    /**
     * Normalizes callback.
     *
     * @param callable|mixed[]|string $callback
     *
     * @throws ReflectionException
     */
    public static function normalize(callable|array|string $callback): callable
    {
        if (is_callable($callback)) {
            return $callback;
        }

        if (is_string($callback)) {
            $callback = explode('::', $callback);
            if (is_callable($callback)) {
                return $callback;
            }
        }

        $method = new ReflectionMethod(...$callback);

        return $method->getClosure(i($method->class));
    }
}
