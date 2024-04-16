<?php declare(strict_types=1);

use SWF\AbstractShared;
use SWF\ConfigGetter;

/**
 * Accesses shared classes.
 *
 * @param class-string<AbstractShared> $className
 */
function shared(string $className): mixed
{
    static $shared = [];

    return $shared[$className] ??= (new ReflectionClass($className))->getMethod('getInstance')->invoke(new $className);
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
