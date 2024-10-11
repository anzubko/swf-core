<?php
declare(strict_types=1);

namespace SWF;

final readonly class CommandDefinition
{
    /**
     * @param CommandArgument[] $arguments
     * @param string[] $argumentsIndex
     * @param CommandOption[] $options
     * @param string[] $optionNames
     * @param string[] $optionShortcuts
     */
    public function __construct(
        private string $method,
        private string $alias,
        private ?string $description = null,
        private array $arguments = [],
        private array $argumentsIndex = [],
        private array $options = [],
        private array $optionNames = [],
        private array $optionShortcuts = [],
    ) {
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return CommandArgument[]
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return string[]
     */
    public function getArgumentsIndex(): array
    {
        return $this->argumentsIndex;
    }

    /**
     * @return CommandOption[]
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return string[]
     */
    public function getOptionNames(): array
    {
        return $this->optionNames;
    }

    /**
     * @return string[]
     */
    public function getOptionShortcuts(): array
    {
        return $this->optionShortcuts;
    }
}
