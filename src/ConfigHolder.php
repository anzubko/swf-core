<?php declare(strict_types=1);

namespace SWF;

use App\Config;

final class ConfigHolder
{
    private static AbstractConfig $config;

    public static function get(): AbstractConfig
    {
        return self::$config ??= new Config;
    }
}
