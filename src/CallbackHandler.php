<?php declare(strict_types=1);

namespace SWF;

use ReflectionException;
use ReflectionMethod;
use function is_array;
use function is_object;
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
        if (is_array($callback)) {
            if (is_object($callback[0] ?? null) && is_callable($callback)) {
                return $callback;
            }
        } elseif (is_string($callback)) {
            if (!str_contains($callback, '::') && is_callable($callback)) {
                return $callback;
            }
            $callback = explode('::', $callback);
        } elseif (is_callable($callback)) {
            return $callback;
        }

        $method = new ReflectionMethod(...$callback);

        return $method->getClosure(i($method->class));
    }
}
