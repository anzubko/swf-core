<?php declare(strict_types=1);

namespace SWF;

use SWF\Enum\CommandTypeEnum;

final readonly class CommandArgument
{
    /**
     * @param string|null $description Optional description
     * @param bool $isRequired Argument will be required.
     * @param bool $isArray If you need multiple arguments.
     * @param value-of<CommandTypeEnum::ALL> $type You will get exactly that type.
     */
    public function __construct(
        public ?string $description = null,
        public bool $isRequired = false,
        public bool $isArray = false,
        public int $type = CommandTypeEnum::STRING,
    ) {
    }
}
