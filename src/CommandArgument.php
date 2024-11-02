<?php
declare(strict_types=1);

namespace SWF;

use SWF\Enum\CommandTypeEnum;

final readonly class CommandArgument extends AbstractCommandParam
{
    /**
     * @param string|null $description Optional description
     * @param bool $required Argument will be required.
     * @param bool $array If you need multiple arguments.
     * @param CommandTypeEnum $type You will get exactly that type.
     */
    public function __construct(
        public ?string $description = null,
        public bool $required = false,
        public bool $array = false,
        public CommandTypeEnum $type = CommandTypeEnum::STRING,
    ) {
    }
}
