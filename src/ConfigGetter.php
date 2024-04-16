<?php declare(strict_types=1);

namespace SWF;

final class ConfigGetter
{
    /**
     * @var mixed[]
     */
    private array $config;

    public function __construct(string $name)
    {
        if ('system' === $name) {
            $this->config = require dirname(__DIR__) . '/config/system.php';

            $overrides = @include APP_DIR . '/config/system.php';
            if (false !== $overrides) {
                $this->config = $overrides + $this->config;
            }
        } else {
            $this->config = require APP_DIR . sprintf('/config/%s.php', $name);
        }
    }

    /**
     * Gets some value by key.
     */
    public function get(string $key): mixed
    {
        return $this->config[$key] ?? null;
    }
}
