<?php declare(strict_types=1);

use SWF\ConfigGetter;
use SWF\RelationProvider;

/**
 * Instantiates some class only once.
 *
 * @param class-string $className
 */
function instance(string $className): mixed
{
    static $instances = [];

    return $instances[$className] ??= method_exists($className, 'getInstance') ? $className::getInstance() : new $className;
}

/**
 * Accesses child classes of some class/interface.
 *
 * @template T
 *
 * @param class-string<T> $className
 *
 * @return array<class-string<T>>
 */
function children(string $className): array
{
    return RelationProvider::getInstance()->getChildren($className);
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
    static $env;

    if (!isset($env)) {
        if (isset($_SERVER['APP_ENV'])) {
            $files = [
                sprintf('/.env.%s.local.php', $_SERVER['APP_ENV']),
                sprintf('/.env.%s.php', $_SERVER['APP_ENV']),
                '/.env.php',
            ];
        } else {
            $files = [
                '/.env.local.php',
                '/.env.php',
            ];
        }

        $env = $_SERVER;
        foreach ($files as $file) {
            $additionEnv = @include APP_DIR . $file;
            if (false !== $additionEnv) {
                $env += $additionEnv;
            }
        }
    }

    return array_key_exists($key, $env) ? $env[$key] : $default;
}
