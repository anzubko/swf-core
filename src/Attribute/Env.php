<?php declare(strict_types=1);

namespace SWF\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class Env
{
    /**
     * Overrides configuration parameter from env file.
     */
    public function __construct(public string $key)
    {
    }
}
