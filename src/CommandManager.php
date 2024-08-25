<?php declare(strict_types=1);

namespace SWF;

use SWF\Enum\CommandTypeEnum;
use SWF\Enum\CommandValueEnum;
use function array_key_exists;
use function count;

final readonly class CommandManager
{
    private const TYPE_TO_PRINTABLE = [
        CommandTypeEnum::INT => 'INT',
        CommandTypeEnum::FLOAT => 'FLOAT',
        CommandTypeEnum::STRING => 'STRING',
        CommandTypeEnum::BOOL => 'BOOL',
    ];

    private CommandDefinition $command;

    /**
     * @param mixed[] $command
     */
    public function __construct(private string $name, array $command)
    {
        $this->command = $this->arrayToCommandDefinition($command);
    }

    public function getName(): string
    {
        return $this->command->name;
    }

    public function processOption(string $chunk): void
    {
        $pair = explode('=', substr($chunk, 2), 2);

        $name = $pair[0];
        if ('' === $name) {
            $this->error(sprintf('malformed option %s', $chunk));
        }
        if (!array_key_exists($name, $this->command->optionNames)) {
            $this->error(sprintf('unknown option --%s', $name));
        }

        $key = $this->command->optionNames[$name];

        $option = $this->command->options[$key];

        $value = $pair[1] ?? null;
        if (null !== $value) {
            if (CommandValueEnum::NONE === $option->value) {
                $this->error(sprintf("option --%s can't have value", $name));
            }

            $this->store($key, $this->typify($option->type, $value), $option->isArray);
        } elseif (CommandValueEnum::NONE === $option->value) {
            $this->store($key, true, $option->isArray);
        } elseif (CommandValueEnum::REQUIRED === $option->value) {
            $this->error(sprintf('option --%s must have value', $name));
        }
    }

    public function processShortOption(string $chunk, ?string $nextChunk): int
    {
        for ($i = 1, $length = mb_strlen($chunk); $i < $length; $i++) {
            $shortcut = mb_substr($chunk, $i, 1);
            if (!array_key_exists($shortcut, $this->command->optionShortcuts)) {
                $this->error(sprintf('unknown option -%s', $shortcut));
            }

            $key = $this->command->optionShortcuts[$shortcut];

            $option = $this->command->options[$key];

            if (CommandValueEnum::NONE === $option->value) {
                $this->store($key, true, $option->isArray);
            } elseif ($length > $i + 1) {
                $this->store($key, $this->typify($option->type, mb_substr($chunk, $i + 1)), $option->isArray);

                return 0;
            } elseif (null !== $nextChunk && ('' === $nextChunk || '-' !== $nextChunk[0])) {
                $this->store($key, $this->typify($option->type, $nextChunk), $option->isArray);

                return 1;
            } elseif (CommandValueEnum::OPTIONAL === $option->value) {
                $this->store($key, null, $option->isArray);

                return 0;
            } elseif (CommandValueEnum::REQUIRED === $option->value) {
                $this->error(sprintf('option -%s must have value', $shortcut));
            }
        }

        return 0;
    }

    public function processArgument(string $chunk): void
    {
        if (count($this->command->arguments) === 0) {
            $this->error('no arguments expected');
        }

        static $index = 0;
        if (!array_key_exists($index, $this->command->argumentsIndex)) {
            $this->error('too many arguments');
        }

        $key = $this->command->argumentsIndex[$index];

        $argument = $this->command->arguments[$key];

        $this->store($key, $this->typify($argument->type, $chunk), $argument->isArray);

        if (!$argument->isArray) {
            $index++;
        }
    }

    public function checkForRequiredParams(): void
    {
        foreach ($this->command->arguments as $key => $argument) {
            if ($argument->isRequired && !array_key_exists($key, $_REQUEST)) {
                $this->error(sprintf('argument %s is required', $key));
            }
        }

        foreach ($this->command->options as $key => $option) {
            if ($option->isRequired && !array_key_exists($key, $_REQUEST)) {
                $this->error(sprintf('option --%s is required', $option->name));
            }
        }
    }

    /**
     * @param mixed[] $command
     */
    private function arrayToCommandDefinition(array $command): CommandDefinition
    {
        foreach ($command['arguments'] as $key => $argument) {
            $command['arguments'][$key] = new CommandArgument(...$argument);
        }

        foreach ($command['optionNames'] as $name => $key) {
            $command['options'][$key]['name'] = $name;
        }

        foreach ($command['optionShortcuts'] as $shortcut => $key) {
            $command['options'][$key]['shortcut'] = ($command['options'][$key]['shortcut'] ?? '') . $shortcut;
        }

        foreach ($command['options'] as $key => $option) {
            $command['options'][$key] = new CommandOption(...$option);
        }

        return new CommandDefinition(...$command);
    }

    private function typify(int $type, string $value): string|int|bool|float
    {
        switch ($type) {
            case CommandTypeEnum::INT:
                if (!filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->error(sprintf('expected an integer value, got "%s"', $value));
                }

                return (int) $value;
            case CommandTypeEnum::FLOAT:
                if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                    $this->error(sprintf('expected an float value, got "%s"', $value));
                }

                return (float) $value;
            case CommandTypeEnum::BOOL:
                return filter_var($value, FILTER_VALIDATE_BOOL);
            default:
                return $value;
        }
    }

    private function store(string $key, mixed $value, bool $isArray): void
    {
        if ($isArray) {
            $_REQUEST[$key][] = $value;
        } else {
            $_REQUEST[$key] = $value;
        }
    }

    private function genUsage(): string
    {
        $optionsUsage = [];
        foreach ($this->command->options as $option) {
            $chunk = sprintf('--%s', $option->name);
            if (null !== $option->shortcut) {
                $chunk = sprintf('-%s|%s', $option->shortcut, $chunk);
            }
            if (CommandValueEnum::NONE !== $option->value) {
                $chunk = sprintf(CommandValueEnum::REQUIRED === $option->value ? '%s=%s' : '%s[=%s]', $chunk, self::TYPE_TO_PRINTABLE[$option->type]);
            }
            if ($option->isArray) {
                $chunk = sprintf('%s...', $chunk);
            }
            if (!$option->isRequired) {
                $chunk = sprintf('[%s]', $chunk);
            }

            $optionsUsage[] = $chunk;
        }

        $argumentsUsage = [];
        foreach ($this->command->arguments as $key => $argument) {
            $chunk = sprintf('<%s:%s>', $key, self::TYPE_TO_PRINTABLE[$argument->type]);
            if ($argument->isArray) {
                $chunk = sprintf('%s...', $chunk);
            }
            if (!$argument->isRequired) {
                $chunk = sprintf('[%s]', $chunk);
            }

            $argumentsUsage[] = $chunk;
        }

        return implode(' ', [$this->name, ...$optionsUsage, ...$argumentsUsage]);
    }

    private function error(string $error): never
    {
        echo sprintf("Usage:\n  %s\n\nError: %s\n", $this->genUsage(), $error);

        exit(1);
    }
}
