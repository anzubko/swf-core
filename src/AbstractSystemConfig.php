<?php declare(strict_types=1);

namespace SWF;

abstract class AbstractSystemConfig extends AbstractConfig
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
     * Basic url (autodetect if null).
     */
    public ?string $url = null;

    /**
     * Default timezone.
     */
    public string $timezone = 'UTC';

    /**
     * Default mode for created directories.
     */
    public int $dirMode = 0777;

    /**
     * Default mode for created/updated files.
     */
    public int $fileMode = 0666;

    /**
     * Namespaces prefixes where can be classes with controllers, commands, listeners or child classes for iterations.
     *
     * @var string[]
     */
    public array $allowedNsPrefixes = ['SWF\\'];

    /**
     * Directory for cache.
     */
    public string $cacheDir = APP_DIR . '/var/cache';

    /**
     * Directory for file based locks.
     */
    public string $locksDir = APP_DIR . '/var/locks';

    /**
     * Custom log file.
     */
    public ?string $customLog = APP_DIR . '/var/log/{ENV}.log';
}
