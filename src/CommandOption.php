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
     * @param bool $isRequired Option will be required.
     * @param bool $isArray If you need multiple option.
     * @param value-of<CommandTypeEnum::ALL> $type You will get exactly that type.
     * @param value-of<CommandValueEnum::ALL> $value Value definition.
     */
    public function __construct(
        public ?string $name = null,
        public ?string $shortcut = null,
        public ?string $description = null,
        public bool $isRequired = false,
        public bool $isArray = false,
        public int $type = CommandTypeEnum::STRING,
        public int $value = CommandValueEnum::OPTIONAL,
    ) {
    }
}
