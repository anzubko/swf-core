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
            throw new InvalidArgumentException(sprintf('Malformed option %s of command %s', $chunk, $this->command->getAlias()));
        }
        if ('help' === $name) {
            return true;
        }
        if (!array_key_exists($name, $this->command->getOptionNames())) {
            throw new InvalidArgumentException(sprintf('Unknown option --%s of command %s', $name, $this->command->getAlias()));
        }

        $key = $this->command->getOptionNames()[$name];

        $option = $this->command->getOptions()[$key];

        $value = $pair[1] ?? null;
        if (null !== $value) {
            if (CommandValueEnum::NONE === $option->getValue()) {
                throw new InvalidArgumentException(sprintf("Option --%s of command %s can't have value", $name, $this->command->getAlias()));
            }
            $this->store($key, $this->typify($option->getType(), $value), $option->isArray());
        } elseif (CommandValueEnum::NONE === $option->getValue()) {
            $this->store($key, true, $option->isArray());
        } elseif (CommandValueEnum::REQUIRED === $option->getValue()) {
            throw new InvalidArgumentException(sprintf('Option --%s of command %s must have value', $name, $this->command->getAlias()));
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
            if (!array_key_exists($shortcut, $this->command->getOptionShortcuts())) {
                throw new InvalidArgumentException(sprintf('Unknown option -%s of command %s', $shortcut, $this->command->getAlias()));
            }

            $key = $this->command->getOptionShortcuts()[$shortcut];

            $option = $this->command->getOptions()[$key];

            if (CommandValueEnum::NONE === $option->getValue()) {
                $this->store($key, true, $option->isArray());
            } elseif ($length > $j + 1) {
                $this->store($key, $this->typify($option->getType(), mb_substr($chunk, $j + 1)), $option->isArray());
                return false;
            } elseif (isset($nChunk) && ('' === $nChunk || '-' !== $nChunk[0])) {
                $this->store($key, $this->typify($option->getType(), $nChunk), $option->isArray());
                $i++;
                return false;
            } elseif (CommandValueEnum::OPTIONAL === $option->getValue()) {
                $this->store($key, null, $option->isArray());
                return false;
            } elseif (CommandValueEnum::REQUIRED === $option->getValue()) {
                throw new InvalidArgumentException(sprintf('Option -%s of command %s must have value', $shortcut, $this->command->getAlias()));
            }
        }

        return false;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function processArgument(string $chunk): bool
    {
        if (count($this->command->getArguments()) === 0) {
            throw new InvalidArgumentException(sprintf('No arguments expected at command %s', $this->command->getAlias()));
        }

        static $index = 0;
        if (!array_key_exists($index, $this->command->getArgumentsIndex())) {
            throw new InvalidArgumentException(sprintf('Too many arguments for command %s', $this->command->getAlias()));
        }

        $key = $this->command->getArgumentsIndex()[$index];

        $argument = $this->command->getArguments()[$key];

        $this->store($key, $this->typify($argument->getType(), $chunk), $argument->isArray());

        if (!$argument->isArray()) {
            $index++;
        }

        return false;
    }

    public function checkForRequires(): void
    {
        foreach ($this->command->getArguments() as $key => $argument) {
            if ($argument->isRequired() && !array_key_exists($key, $_REQUEST)) {
                throw new InvalidArgumentException(sprintf('Argument %s of command %s is required', $key, $this->command->getAlias()));
            }
        }

        foreach ($this->command->getOptions() as $key => $option) {
            if ($option->isRequired() && !array_key_exists($key, $_REQUEST)) {
                throw new InvalidArgumentException(sprintf('Option --%s of command %s is required', $option->getName(), $this->command->getAlias()));
            }
        }
    }

    private function typify(CommandTypeEnum $type, string $value): string|int|bool|float
    {
        switch ($type) {
            case CommandTypeEnum::INT:
                if (!filter_var($value, FILTER_VALIDATE_INT)) {
                    throw new InvalidArgumentException(sprintf('Expected an integer value, got "%s" for command %s', $value, $this->command->getAlias()));
                }
                return (int) $value;
            case CommandTypeEnum::FLOAT:
                if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                    throw new InvalidArgumentException(sprintf('Expected an float value, got "%s" for command %s', $value, $this->command->getAlias()));
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
