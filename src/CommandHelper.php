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
    private const EXTRA_OPTIONS = [
        'quiet' => [
            'name' => 'quiet',
            'shortcut' => 'q',
            'type' => CommandTypeEnum::BOOL->value,
            'value' => CommandValueEnum::NONE->value,
            'hidden' => true,
        ],
        'help' => [
            'name' => 'help',
            'shortcut' => 'h',
            'type' => CommandTypeEnum::BOOL->value,
            'value' => CommandValueEnum::NONE->value,
            'hidden' => true,
        ],
    ];

    /**
     * @param mixed[] $command
     */
    public function arrayToCommandDefinition(string $alias, array $command): CommandDefinition
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

        foreach (self::EXTRA_OPTIONS as $key => $option) {
            $command['optionNames'][$option['name']] = $key;
            $command['optionShortcuts'][$option['shortcut']] = $key;
            $command['options'][$key] = $option;
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

        $command['alias'] = $alias;

        return new CommandDefinition(...$command);
    }
}
