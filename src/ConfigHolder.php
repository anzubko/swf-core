<?php declare(strict_types=1);

namespace SWF;

final class ConfigHolder
{
    private static AbstractConfig $config;

    /**
     * Sets config instance.
     */
    public static function set(AbstractConfig $config): void
    {
        self::$config = $config;
    }

    /**
     * Gets config instance.
     */
    public static function get(): AbstractConfig
    {
        return self::$config;
    }
}
