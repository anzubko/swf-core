<?php

return [
    /**
     * Environment mode ('dev', 'test', 'prod', etc..).
     *
     * string
     */
    'env' => 'dev',

    /**
     * Debug mode (not minify HTML/CSS/JS if true).
     *
     * bool
     */
    'debug' => false,

    /**
     * Treats errors except deprecations and notices as fatal and sets Twig to strict mode.
     *
     * bool
     */
    'strict' => true,

    /**
     * Basic url (autodetect if null).
     *
     * string|null
     */
    'url' => null,

    /**
     * Default timezone.
     *
     * string
     */
    'timezone' => 'UTC',

    /**
     * Namespaces where can be classes with controllers, commands, listeners, etc...
     *
     * array
     */
    'namespaces' => ['App\\'],

    /**
     * Default mode for created directories.
     *
     * int
     */
    'dirMode' => 0777,

    /**
     * Default mode for created/updated files.
     *
     * int
     */
    'fileMode' => 0666,
];
