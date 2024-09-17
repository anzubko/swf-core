<?php declare(strict_types=1);

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
        if ('' === $name) {
            throw new InvalidArgumentException(sprintf('Malformed option %s', $chunk));
        }
        if ('help' === $name) {
            return true;
        }
        if (!array_key_exists($name, $this->command->optionNames)) {
            throw new InvalidArgumentException(sprintf('Unknown option --%s', $name));
        }

        $key = $this->command->optionNames[$name];

        $option = $this->command->options[$key];

        $value = $pair[1] ?? null;
        if (null !== $value) {
            if (CommandValueEnum::NONE === $option->value) {
                throw new InvalidArgumentException(sprintf("Option --%s can't have value", $name));
            }

            $this->store($key, $this->typify($option->type, $value), $option->isArray);
        } elseif (CommandValueEnum::NONE === $option->value) {
            $this->store($key, true, $option->isArray);
        } elseif (CommandValueEnum::REQUIRED === $option->value) {
            throw new InvalidArgumentException(sprintf('Option --%s must have value', $name));
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
            if ('h' === $shortcut) {
                return true;
            }
            if (!array_key_exists($shortcut, $this->command->optionShortcuts)) {
                throw new InvalidArgumentException(sprintf('Unknown option -%s', $shortcut));
            }

            $key = $this->command->optionShortcuts[$shortcut];

            $option = $this->command->options[$key];

            if (CommandValueEnum::NONE === $option->value) {
                $this->store($key, true, $option->isArray);
            } elseif ($length > $j + 1) {
                $this->store($key, $this->typify($option->type, mb_substr($chunk, $j + 1)), $option->isArray);
                return false;
            } elseif (isset($nChunk) && ('' === $nChunk || '-' !== $nChunk[0])) {
                $this->store($key, $this->typify($option->type, $nChunk), $option->isArray);
                $i++;
                return false;
            } elseif (CommandValueEnum::OPTIONAL === $option->value) {
                $this->store($key, null, $option->isArray);
                return false;
            } elseif (CommandValueEnum::REQUIRED === $option->value) {
                throw new InvalidArgumentException(sprintf('Option -%s must have value', $shortcut));
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
            throw new InvalidArgumentException('No arguments expected');
        }

        static $index = 0;
        if (!array_key_exists($index, $this->command->argumentsIndex)) {
            throw new InvalidArgumentException('Too many arguments');
        }

        $key = $this->command->argumentsIndex[$index];

        $argument = $this->command->arguments[$key];

        $this->store($key, $this->typify($argument->type, $chunk), $argument->isArray);

        if (!$argument->isArray) {
            $index++;
        }

        return false;
    }

    public function checkForRequires(): void
    {
        foreach ($this->command->arguments as $key => $argument) {
            if ($argument->isRequired && !array_key_exists($key, $_REQUEST)) {
                throw new InvalidArgumentException(sprintf('Argument %s is required', $key));
            }
        }

        foreach ($this->command->options as $key => $option) {
            if ($option->isRequired && !array_key_exists($key, $_REQUEST)) {
                throw new InvalidArgumentException(sprintf('Option --%s is required', $option->name));
            }
        }
    }

    private function typify(CommandTypeEnum $type, string $value): string|int|bool|float
    {
        switch ($type) {
            case CommandTypeEnum::INT:
                if (!filter_var($value, FILTER_VALIDATE_INT)) {
                    throw new InvalidArgumentException(sprintf('Expected an integer value, got "%s"', $value));
                }

                return (int) $value;
            case CommandTypeEnum::FLOAT:
                if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                    throw new InvalidArgumentException(sprintf('Expected an float value, got "%s"', $value));
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
}
