<?php declare(strict_types=1);

namespace SWF;

use function array_key_exists;

final class ConfigGetter
{
    private static self $instance;

    /**
     * @var mixed[][]
     */
    private array $configs = [];

    private function __construct()
    {
        $system = require dirname(__DIR__) . '/config/system.php';

        $overrides = @include APP_DIR . '/config/system.php';
        if (false !== $overrides) {
            $system = $overrides + $system;
        }

        $this->configs['system'] = $system;
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Gets some value by config name and key.
     */
    public function get(string $configName, string $key, mixed $default = null): mixed
    {
        $this->configs[$configName] ??= require APP_DIR . sprintf('/config/%s.php', $configName);

        return array_key_exists($key, $this->configs[$configName]) ? $this->configs[$configName][$key] : $default;
    }
}
