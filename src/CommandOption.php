<?php declare(strict_types=1);

namespace SWF;

use SWF\Enum\CommandTypeEnum;
use SWF\Enum\CommandValueEnum;

final readonly class CommandOption
{
    /**
     * @param string|null $name
     * @param string|null $shortcut
     * @param string|null $description
     * @param bool $isRequired
     * @param bool $isArray
     * @param int $type
     * @param int $value
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
