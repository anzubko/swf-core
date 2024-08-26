<?php declare(strict_types=1);

namespace SWF\Attribute;

use Attribute;
use SWF\CommandArgument;
use SWF\CommandOption;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class AsCommand
{
    /**
     * Registers command.
     *
     * @param string $name Name of command.
     * @param string|null $description Optional command description.
     * @param array<CommandOption|CommandArgument> $params Parameters of command line, what will be parsed into $_REQUEST array.
     */
    public function __construct(
        public string $name,
        public ?string $description = null,
        public array $params = [],
    ) {
    }
}
