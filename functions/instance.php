<?php declare(strict_types=1);

use SWF\InstanceStorage;

/**
 * Instantiates some class only once.
 *
 * @param class-string $className
 */
function i(string $className): mixed
{
    return InstanceStorage::$instances[$className] ??= method_exists($className, 'getInstance') ? $className::getInstance() : new $className();
}
