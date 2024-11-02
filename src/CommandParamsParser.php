<?php
declare(strict_types=1);

namespace SWF;

use InvalidArgumentException;
use SWF\Enum\CommandTypeEnum;
use SWF\Enum\CommandValueEnum;
use function array_key_exists;
use function count;

/**
 * @internal
 */
final readonly class CommandParamsParser
{
    public function __construct(
        private CommandDefinition $command,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function processOption(string $chunk): bool
    {
        $pair = explode('=', substr($chunk, 2), 2);

        $name = $pair[0];
        if ($name === '') {
            throw new InvalidArgumentException(sprintf('Malformed option %s of command %s', $chunk, $this->command->alias));
        }
        if ($name === 'help') {
            return true;
        }
        if (!array_key_exists($name, $this->command->optionNames)) {
            throw new InvalidArgumentException(sprintf('Unknown option --%s of command %s', $name, $this->command->alias));
        }

        $key = $this->command->optionNames[$name];

        $option = $this->command->options[$key];

        $value = $pair[1] ?? null;
        if ($value !== null) {
            if ($option->value === CommandValueEnum::NONE) {
                throw new InvalidArgumentException(sprintf("Option --%s of command %s can't have value", $name, $this->command->alias));
            }
            $this->store($key, $this->typify($option->type, $value), $option->array);
        } elseif ($option->value === CommandValueEnum::NONE) {
            $this->store($key, true, $option->array);
        } elseif ($option->value === CommandValueEnum::REQUIRED) {
            throw new InvalidArgumentException(sprintf('Option --%s of command %s must have value', $name, $this->command->alias));
        }

        return false;
    }

    /**
     * @param string[] $chunks
     *
     * @throws InvalidArgumentException
     */
    public function processShortOption(int &$i, array $chunks): bool
    {
        for ($j = 1, $chunk = $chunks[$i], $nChunk = $chunks[$i + 1] ?? null, $length = mb_strlen($chunk); $j < $length; $j++) {
            $shortcut = mb_substr($chunk, $j, 1);
            if ($shortcut === 'h') {
                return true;
            }
            if (!array_key_exists($shortcut, $this->command->optionShortcuts)) {
                throw new InvalidArgumentException(sprintf('Unknown option -%s of command %s', $shortcut, $this->command->alias));
            }

            $key = $this->command->optionShortcuts[$shortcut];

            $option = $this->command->options[$key];

            if ($option->value === CommandValueEnum::NONE) {
                $this->store($key, true, $option->array);
            } elseif ($length > $j + 1) {
                $this->store($key, $this->typify($option->type, mb_substr($chunk, $j + 1)), $option->array);
                return false;
            } elseif (isset($nChunk) && ($nChunk === '' || $nChunk[0] !== '-')) {
                $this->store($key, $this->typify($option->type, $nChunk), $option->array);
                $i++;
                return false;
            } elseif ($option->value === CommandValueEnum::OPTIONAL) {
                $this->store($key, null, $option->array);
                return false;
            } elseif ($option->value === CommandValueEnum::REQUIRED) {
                throw new InvalidArgumentException(sprintf('Option -%s of command %s must have value', $shortcut, $this->command->alias));
            }
        }

        return false;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function processArgument(string $chunk): bool
    {
        if (count($this->command->arguments) === 0) {
            throw new InvalidArgumentException(sprintf('No arguments expected at command %s', $this->command->alias));
        }

        static $index = 0;
        if (!array_key_exists($index, $this->command->argumentsIndex)) {
            throw new InvalidArgumentException(sprintf('Too many arguments for command %s', $this->command->alias));
        }

        $key = $this->command->argumentsIndex[$index];

        $argument = $this->command->arguments[$key];

        $this->store($key, $this->typify($argument->type, $chunk), $argument->array);

        if (!$argument->array) {
            $index++;
        }

        return false;
    }

    public function checkForRequires(): void
    {
        foreach ($this->command->arguments as $key => $argument) {
            if ($argument->required && !array_key_exists($key, $_REQUEST)) {
                throw new InvalidArgumentException(sprintf('Argument %s of command %s is required', $key, $this->command->alias));
            }
        }

        foreach ($this->command->options as $key => $option) {
            if ($option->required && !array_key_exists($key, $_REQUEST)) {
                throw new InvalidArgumentException(sprintf('Option --%s of command %s is required', $option->name, $this->command->alias));
            }
        }
    }

    private function typify(CommandTypeEnum $type, string $value): string|int|bool|float
    {
        switch ($type) {
            case CommandTypeEnum::INT:
                if (!filter_var($value, FILTER_VALIDATE_INT)) {
                    throw new InvalidArgumentException(sprintf('Expected an integer value, got "%s" for command %s', $value, $this->command->alias));
                }
                return (int) $value;
            case CommandTypeEnum::FLOAT:
                if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                    throw new InvalidArgumentException(sprintf('Expected an float value, got "%s" for command %s', $value, $this->command->alias));
                }
                return (float) $value;
            case CommandTypeEnum::BOOL:
                return filter_var($value, FILTER_VALIDATE_BOOL);
            default:
                return $value;
        }
    }

    private function store(string $key, mixed $value, bool $array): void
    {
        if ($array) {
            $_REQUEST[$key][] = $value;
        } else {
            $_REQUEST[$key] = $value;
        }
    }
}
