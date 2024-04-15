<?php declare(strict_types=1);

namespace SWF;

final class InstanceHolder
{
    /**
     * @var mixed[]
     */
    private static array $instances = [];

    /**
     * @template T
     *
     * @param class-string<T> $className
     *
     * @return T
     */
    public static function get(string $className)
    {
        return self::$instances[$className] ??= new $className;
    }
}
