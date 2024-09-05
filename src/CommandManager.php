<?php declare(strict_types=1);

namespace SWF;

use SWF\Enum\CommandTypeEnum;
use SWF\Enum\CommandValueEnum;
use function array_key_exists;
use function count;

final readonly class CommandManager
{
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
        if ('help' === $name) {
            $this->showHelp();
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
            if ('h' === $shortcut) {
                $this->showHelp();
            }
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

    private function typify(CommandTypeEnum $type, string $value): string|int|bool|float
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

    private function genUsage(bool $withHelp = true): string
    {
        $argumentsUsage = $optionsUsage = [];

        foreach ($this->command->arguments as $key => $argument) {
            $chunk = sprintf('<%s:%s>', $key, $argument->type->name);
            if ($argument->isArray) {
                $chunk = sprintf('%s...', $chunk);
            }
            if (!$argument->isRequired) {
                $chunk = sprintf('[%s]', $chunk);
            }

            $argumentsUsage[] = $chunk;
        }

        foreach ($this->command->options as $option) {
            if (null === $option->shortcut) {
                $chunk = sprintf('--%s', $option->name);
            } else {
                $chunk = sprintf('-%s|--%s', $option->shortcut, $option->name);
            }
            if (CommandValueEnum::REQUIRED === $option->value) {
                $chunk = sprintf('%s=%s', $chunk, $option->type->name);
            } elseif (CommandValueEnum::OPTIONAL === $option->value) {
                $chunk = sprintf('%s[=%s]', $chunk, $option->type->name);
            }
            if ($option->isArray) {
                $chunk = sprintf('%s...', $chunk);
            }
            if (!$option->isRequired) {
                $chunk = sprintf('[%s]', $chunk);
            }

            $optionsUsage[] = $chunk;
        }

        if ($withHelp) {
            $optionsUsage[] = '[-h|--help]';
        }

        return implode(' ', [$this->name, ...$optionsUsage, ...$argumentsUsage]);
    }

    private function showHelp(): never
    {
        $maxLength = 0;
        $arguments = $options = [];

        foreach ($this->command->arguments as $key => $argument) {
            $arguments[$key] = (string) $key;

            $maxLength = max($maxLength, mb_strlen((string) $key));
        }

        foreach ($this->command->options as $key => $option) {
            if (null === $option->shortcut) {
                $chunk = sprintf('    --%s', $option->name);
            } else {
                $chunk = sprintf('-%s, --%s', $option->shortcut, $option->name);
            }

            $options[$key] = $chunk;

            $maxLength = max($maxLength, mb_strlen($chunk));
        }

        foreach ($arguments as $key => $argument) {
            if (null !== $this->command->arguments[$key]->description) {
                $arguments[$key] = mb_str_pad($argument, $maxLength + 2) . $this->command->arguments[$key]->description;
            }
        }

        foreach ($options as $key => $option) {
            if (null !== $this->command->options[$key]->description) {
                $options[$key] = mb_str_pad($option, $maxLength + 2) . $this->command->options[$key]->description;
            }
        }

        if (null !== $this->command->description) {
            echo sprintf("Description:\n  %s\n\n", $this->command->description);
        }

        echo sprintf("Usage:\n  %s\n", $this->genUsage(false));

        if (count($arguments) > 0) {
            echo sprintf("\nArguments:\n  %s\n", implode("\n  ", $arguments));
        }

        if (count($options) > 0) {
            echo sprintf("\nOptions:\n  %s\n", implode("\n  ", $options));
        }

        exit(0);
    }

    private function error(string $error): never
    {
        echo sprintf("Usage:\n  %s\n\nError: %s\n", $this->genUsage(), $error);

        exit(1);
    }
}
