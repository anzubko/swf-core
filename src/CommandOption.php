<?php
declare(strict_types=1);

namespace SWF;

use SWF\Enum\CommandTypeEnum;
use SWF\Enum\CommandValueEnum;

final readonly class CommandOption extends AbstractCommandParam
{
    /**
     * @param string|null $name Name of option. If null, then key will be used.
     * @param string|null $shortcut One character shortcut.
     * @param string|null $description Optional description.
     * @param bool $required Option will be required.
     * @param bool $array If you need multiple option.
     * @param bool $hidden Hide this option at options section of help page.
     * @param CommandTypeEnum $type You will get exactly that type.
     * @param CommandValueEnum $value Value definition.
     */
    public function __construct(
        public ?string $name = null,
        public ?string $shortcut = null,
        public ?string $description = null,
        public bool $required = false,
        public bool $array = false,
        public bool $hidden = false,
        public CommandTypeEnum $type = CommandTypeEnum::STRING,
        public CommandValueEnum $value = CommandValueEnum::OPTIONAL,
    ) {
    }
}
