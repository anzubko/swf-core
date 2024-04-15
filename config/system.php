<?php

return [
    /**
     * Environment mode ('dev', 'test', 'prod', etc..).
     */
    'env' => 'dev',

    /**
     * Debug mode (not minify HTML/CSS/JS if true).
     */
    'debug' => false,

    /**
     * Treats errors except deprecations and notices as fatal and sets Twig to strict mode.
     */
    'strict' => true,

    /**
     * Basic url (autodetect if null).
     */
    'url' => null,

    /**
     * Default timezone.
     */
    'timezone' => 'UTC',

    /**
     * Default mode for created directories.
     */
    'dirMode' => 0777,

    /**
     * Default mode for created/updated files.
     */
    'fileMode' => 0666,
];
