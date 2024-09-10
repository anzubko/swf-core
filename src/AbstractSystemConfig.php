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
     * Namespaces where can be classes with controllers, commands, listeners or child classes for iterations.
     *
     * @var string[]
     */
    public array $namespaces = [];

    /**
     * Custom log file.
     */
    public ?string $customLog = null;
}
