<?php declare(strict_types=1);

namespace SWF;

use ReflectionClass;
use ReflectionProperty;
use SWF\Attribute\Env;
use function array_key_exists;
use function is_array;

final class ConfigHolder
{
    private static AbstractConfig $config;

    public static function set(AbstractConfig $config): void
    {
        $env = self::getEnv();

        self::$config = $config;
        foreach ((new ReflectionClass(self::$config))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            foreach ($property->getAttributes(Env::class) as $attribute) {
                $key = $attribute->newInstance()->key;
                if (!array_key_exists($key, $env)) {
                    continue;
                }

                $value = $property->getValue(self::$config);
                if (is_array($value) && is_array($env[$key])) {
                    $property->setValue(self::$config, $env[$key] + $value);
                } else {
                    $property->setValue(self::$config, $env[$key]);
                }
            }
        }
    }

    /**
     * Gets config instance.
     */
    public static function get(): AbstractConfig
    {
        return self::$config;
    }

    /**
     * @return mixed[]
     */
    private static function getEnv(): array
    {
        if (isset($_SERVER['APP_ENV'])) {
            $env = @include sprintf('%s/.env.%s.php', APP_DIR, $_SERVER['APP_ENV']);

            $localEnv = @include sprintf('%s/.env.%s.local.php', APP_DIR, $_SERVER['APP_ENV']);
        } else {
            $env = @include sprintf('%s/.env.php', APP_DIR);

            $localEnv = @include sprintf('%s/.env.local.php', APP_DIR);
        }

        if (!is_array($env)) {
            $env = [];
        }

        if (!is_array($localEnv)) {
            $localEnv = [];
        }

        return $localEnv + $env;
    }
}
