<?php declare(strict_types=1);

namespace SWF;

use SWF\Enum\CommandTypeEnum;
use SWF\Enum\CommandValueEnum;
use function array_key_exists;

/**
 * @internal
 */
final class CommandHelper
{
    /**
     * @param mixed[] $command
     */
    public function arrayToCommandDefinition(array $command): CommandDefinition
    {
        if (array_key_exists('arguments', $command)) {
            foreach ($command['arguments'] as $key => $argument) {
                if (array_key_exists('type', $argument)) {
                    $argument['type'] = CommandTypeEnum::from($argument['type']);
                }

                $command['arguments'][$key] = new CommandArgument(...$argument);
            }
        }

        if (array_key_exists('optionNames', $command)) {
            foreach ($command['optionNames'] as $name => $key) {
                $command['options'][$key]['name'] = $name;
            }
        }

        if (array_key_exists('optionShortcuts', $command)) {
            foreach ($command['optionShortcuts'] as $shortcut => $key) {
                $command['options'][$key]['shortcut'] = $shortcut;
            }
        }

        if (array_key_exists('options', $command)) {
            foreach ($command['options'] as $key => $option) {
                if (array_key_exists('type', $option)) {
                    $option['type'] = CommandTypeEnum::from($option['type']);
                }
                if (array_key_exists('value', $option)) {
                    $option['value'] = CommandValueEnum::from($option['value']);
                }

                $command['options'][$key] = new CommandOption(...$option);
            }
        }

        return new CommandDefinition(...$command);
    }
}
