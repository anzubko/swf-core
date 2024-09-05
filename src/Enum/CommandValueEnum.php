<?php declare(strict_types=1);

namespace SWF\Enum;

enum CommandValueEnum: int
{
    case REQUIRED = 1;
    case OPTIONAL = 2;
    case NONE = 3;
}
