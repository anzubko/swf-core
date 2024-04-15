<?php declare(strict_types=1);

namespace SWF;

use function array_key_exists;

final class ConfigProvider
{
    /**
     * @var mixed[][]
     */
    private static array $configs = [];

    /**
     * Gets some value by config name and key.
     */
    public static function get(string $configName, string $key, mixed $default = null): mixed
    {
        self::$configs[$configName] ??= self::load($configName);

        return array_key_exists($key, self::$configs[$configName]) ? self::$configs[$configName][$key] : $default;
    }

    /**
     * @return mixed[]
     */
    private static function load(string $configName): array
    {
        if ('system' === $configName) {
            $config = require dirname(__DIR__) . '/config/system.php';

            $overrides = @include APP_DIR . '/config/system.php';
            if (false !== $overrides) {
                $config = $overrides + $config;
            }
        } else {
            $config = require APP_DIR . sprintf('/config/%s.php', $configName);
        }

        return $config;
    }
}
