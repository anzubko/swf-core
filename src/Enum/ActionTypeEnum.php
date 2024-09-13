<?php declare(strict_types=1);

namespace SWF\Enum;

enum ActionTypeEnum: string
{
    case CONTROLLER = 'controller';
    case COMMAND = 'command';
}
