<?php
declare(strict_types=1);

namespace SWF\Attribute;

use Attribute;
use SWF\AbstractCommandParam;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class AsCommand
{
    /**
     * Registers command.
     *
     * @param string $alias Alias of command for use in command line.
     * @param string|null $description Optional description.
     * @param array<string, AbstractCommandParam> $params Parameters of command line, what will be parsed into $_REQUEST array.
     */
    public function __construct(
        private string $alias,
        private ?string $description = null,
        private array $params = [],
    ) {
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return array<string, AbstractCommandParam>
     */
    public function getParams(): array
    {
        return $this->params;
    }
}
