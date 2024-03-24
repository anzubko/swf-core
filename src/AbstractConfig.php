<?php declare(strict_types=1);

namespace SWF;

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
     * Lock files pattern.
     */
    public string $lockFile = APP_DIR . '/var/locks/{KEY}.lock';

    /**
     * Additional errors log file.
     */
    public ?string $errorLog = APP_DIR . '/var/log/errors.log';

    /**
     * System cache directory.
     */
    public string $sysCacheDir = APP_DIR . '/var/cache/system';
}
