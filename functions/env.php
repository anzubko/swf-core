<?php declare(strict_types=1);

use SWF\EnvGetter;

/**
 * Accesses server parameters and parameters from .env files.
 */
function env(string $key, mixed $default = null): mixed
{
    return EnvGetter::getInstance()->get($key, $default);
}
