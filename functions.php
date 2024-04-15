<?php declare(strict_types=1);

/**
 * Returns value by key from .env files.
 */
function env(string $key, mixed $default = null): mixed
{
    static $env;
    if (!isset($env)) {
        if (isset($_SERVER['APP_ENV'])) {
            $env = @include APP_DIR . sprintf('/.env.%s.php', $_SERVER['APP_ENV']);

            $localEnv = @include APP_DIR . sprintf('/.env.%s.local.php', $_SERVER['APP_ENV']);
        } else {
            $env = @include APP_DIR . '/.env.php';

            $localEnv = @include APP_DIR . '/.env.local.php';
        }

        if (false === $env) {
            $env = [];
        }

        if (false !== $localEnv) {
            $env = $localEnv + $env;
        }
    }

    return array_key_exists($key, $env) ? $env[$key] : $default;
}
