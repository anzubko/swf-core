<?php declare(strict_types=1);

use SWF\ConfigGetter;

/**
 * Accesses configs.
 */
function config(string $name): ConfigGetter
{
    static $configs = [];

    return $configs[$name] ??= new ConfigGetter($name);
}
