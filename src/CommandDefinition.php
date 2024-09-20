<?php declare(strict_types=1);

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
        public string $action,
        public string $alias,
        public ?string $description = null,
        public array $arguments = [],
        public array $argumentsIndex = [],
        public array $options = [],
        public array $optionNames = [],
        public array $optionShortcuts = [],
    ) {
    }
}
