<?php declare(strict_types=1);

namespace SWF;

use function array_key_exists;

final class EnvGetter
{
    /**
     * @var mixed[]
     */
    private array $env;

    private static self $instance;

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
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

        $this->env = $_SERVER;
        foreach ($files as $file) {
            $additionEnv = @include APP_DIR . $file;
            if (false !== $additionEnv) {
                $this->env += $additionEnv;
            }
        }
    }

    /**
     * Accesses server parameters and parameters from .env files.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->env) ? $this->env[$key] : $default;
    }
}
