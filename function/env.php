<?php declare(strict_types=1);

/**
 * Returns value by key from .env files.
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
