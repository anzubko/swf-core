<?php declare(strict_types=1);

use SWF\InstanceStorage;

/**
 * Instantiates some class only once.
 *
 * @param class-string $class
 */
function i(string $class): mixed
{
    return InstanceStorage::$instances[$class] ??= method_exists($class, 'getInstance') ? $class::getInstance() : new $class();
}
