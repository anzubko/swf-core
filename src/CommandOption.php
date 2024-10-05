<?php declare(strict_types=1);

namespace SWF;

use SWF\Enum\CommandTypeEnum;
use SWF\Enum\CommandValueEnum;

final readonly class CommandOption
{
    /**
     * @param string|null $name Name of option. If null, then key will be used.
     * @param string|null $shortcut One character shortcut.
     * @param string|null $description Optional description.
     * @param bool $required Option will be required.
     * @param bool $array If you need multiple option.
     * @param CommandTypeEnum $type You will get exactly that type.
     * @param CommandValueEnum $value Value definition.
     */
    public function __construct(
        private ?string $name = null,
        private ?string $shortcut = null,
        private ?string $description = null,
        private bool $required = false,
        private bool $array = false,
        private CommandTypeEnum $type = CommandTypeEnum::STRING,
        private CommandValueEnum $value = CommandValueEnum::OPTIONAL,
    ) {
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getShortcut(): ?string
    {
        return $this->shortcut;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function isArray(): bool
    {
        return $this->array;
    }

    public function getType(): CommandTypeEnum
    {
        return $this->type;
    }

    public function getValue(): CommandValueEnum
    {
        return $this->value;
    }
}
