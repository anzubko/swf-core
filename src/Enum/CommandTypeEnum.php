<?php declare(strict_types=1);

namespace SWF\Enum;

class CommandTypeEnum
{
    public const INT = 1;
    public const FLOAT = 2;
    public const STRING = 3;
    public const BOOL = 4;

    public const ALL = [
        self::INT,
        self::FLOAT,
        self::STRING,
        self::BOOL,
    ];
}
