<?php declare(strict_types=1);

namespace SWF\Enum;

class CommandValueEnum
{
    public const REQUIRED = 1;
    public const OPTIONAL = 2;
    public const NONE = 3;

    public const ALL = [
        self::REQUIRED,
        self::OPTIONAL,
        self::NONE,
    ];
}
