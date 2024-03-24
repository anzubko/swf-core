<?php declare(strict_types=1);

namespace SWF\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class AsCommand
{
    /**
     * Registers command.
     */
    public function __construct(public string $name)
    {
    }
}
