<?php declare(strict_types=1);

namespace SWF;

use function array_key_exists;

final class ConfigGetter
{
    /**
     * @var mixed[][]
     */
    private static array $configs = [];

    public static function get(string $configName, string $key, mixed $default = null): mixed
    {
        self::$configs[$configName] ??= require APP_DIR . sprintf('/config/%s.php', $configName);

        return array_key_exists($key, self::$configs[$configName]) ? self::$configs[$configName][$key] : $default;
    }
}
