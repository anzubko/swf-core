<?php
declare(strict_types=1);

namespace SWF\Attribute;

use Attribute;
use SWF\AbstractCommandParam;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
readonly class AsCommand
{
    /**
     * Registers command.
     *
     * @param string $alias Alias of command for use in command line.
     * @param string|null $description Optional description.
     * @param array<string, AbstractCommandParam> $params Parameters of command line, what will be parsed into $_REQUEST array.
     */
    public function __construct(
        public string $alias,
        public ?string $description = null,
        public array $params = [],
    ) {
    }
}
