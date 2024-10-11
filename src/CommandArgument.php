<?php
declare(strict_types=1);

namespace SWF;

use SWF\Enum\CommandTypeEnum;

final readonly class CommandArgument
{
    /**
     * @param string|null $description Optional description
     * @param bool $required Argument will be required.
     * @param bool $array If you need multiple arguments.
     * @param CommandTypeEnum $type You will get exactly that type.
     */
    public function __construct(
        private ?string $description = null,
        private bool $required = false,
        private bool $array = false,
        private CommandTypeEnum $type = CommandTypeEnum::STRING,
    ) {
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
}
