<?php declare(strict_types=1);

namespace SWF;

use ReflectionClass;
use ReflectionProperty;
use SWF\Attribute\Env;
use function array_key_exists;
use function is_array;

abstract class AbstractConfig
{
    /**
     * Environment mode ('dev', 'test', 'prod', etc..).
     */
    public string $env = 'dev';

    /**
     * Debug mode (not minify HTML/CSS/JS if true).
     */
    public bool $debug = false;

    /**
     * Treats errors except deprecations and notices as fatal.
     */
    public bool $strict = true;

    /**
     * Basic url (autodetect if null).
     */
    public ?string $url = null;

    /**
     * Default timezone.
     */
    public string $timezone = 'UTC';

    /**
     * How many times retry failed transactions with expected sql states.
     */
    public int $transactionRetries = 7;

    /**
     * Optional error document file.
     */
    public ?string $errorDocument = null;

    /**
     * Compress output if size more this value in bytes.
     */
    public int $compressMin = 32 * 1024;

    /**
     * Compress output with only these mime types.
     *
     * @var string[]
     */
    public array $compressMimes = [];

    /**
     * Default mode for new directories.
     */
    public int $dirMode = 0777;

    /**
     * Default mode for new/updated files.
     */
    public int $fileMode = 0666;

    /**
     * Additional errors log file.
     */
    public ?string $errorLog = APP_DIR . '/var/log/errors.log';

    /**
     * Config will be automatically merged with .env configs via attributes Env.
     */
    final public function __construct()
    {
        if (isset($_SERVER['APP_ENV'])) {
            $env = @include APP_DIR . sprintf('/.env.%s.php', $_SERVER['APP_ENV']);

            $localEnv = @include APP_DIR . sprintf('/.env.%s.local.php', $_SERVER['APP_ENV']);
        } else {
            $env = @include APP_DIR . '/.env.php';

            $localEnv = @include APP_DIR . '/.env.local.php';
        }

        if (false === $env) {
            $env = [];
        }

        if (false !== $localEnv) {
            $env = $localEnv + $env;
        }

        foreach ((new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            foreach ($property->getAttributes(Env::class) as $attribute) {
                $envKey = $attribute->newInstance()->key;
                if (!array_key_exists($envKey, $env)) {
                    continue;
                }

                $name = $property->name;
                if (is_array($this->{$name}) && is_array($env[$envKey])) {
                    $this->{$name} = $env[$envKey] + $this->{$name};
                } else {
                    $this->{$name} = $env[$envKey];
                }
            }
        }
    }
}
