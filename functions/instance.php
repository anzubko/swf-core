<?php declare(strict_types=1);

use SWF\InstanceStorage;

/**
 * Instantiates some class only once.
 *
 * @template T
 *
 * @param class-string<T> $className
 *
 * @return T
 */
function i(string $className)
{
    return InstanceStorage::$instances[$className] ??= method_exists($className, 'getInstance') ? $className::getInstance() : new $className();
}
