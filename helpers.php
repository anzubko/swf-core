<?php declare(strict_types=1);

use SWF\ConfigGetter;
use SWF\EnvGetter;

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

/**
 * Accesses configs.
 */
function config(string $name): ConfigGetter
{
    static $configs = [];

    return $configs[$name] ??= new ConfigGetter($name);
}

/**
 * Accesses server parameters and parameters from .env files.
 */
function env(string $key, mixed $default = null): mixed
{
    return EnvGetter::getInstance()->get($key, $default);
}
