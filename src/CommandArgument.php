<?php declare(strict_types=1);

namespace SWF;

use SWF\Enum\CommandTypeEnum;

final readonly class CommandArgument
{
    /**
     * @param string|null $description
     * @param bool $isRequired
     * @param bool $isArray
     * @param value-of<CommandTypeEnum::ALL> $type
     */
    public function __construct(
        public ?string $description = null,
        public bool $isRequired = false,
        public bool $isArray = false,
        public int $type = CommandTypeEnum::STRING,
    ) {
    }
}
