<?php declare(strict_types=1);

/**
 * Instantiates some class only once.
 *
 * @param class-string $className
 */
function i(string $className): mixed
{
    static $instances = [];

    return $instances[$className] ??= method_exists($className, 'getInstance') ? $className::getInstance() : new $className;
}
