<?php
declare(strict_types=1);

namespace SWF\Enum;

enum CommandTypeEnum: int
{
    case INT = 1;
    case FLOAT = 2;
    case STRING = 3;
    case BOOL = 4;
}
