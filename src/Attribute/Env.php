<?php declare(strict_types=1);

namespace SWF\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final readonly class Env
{
    /**
     * Overrides configuration property by some ENV value.
     *
     * @param string $key Key of some ENV parameter.
     */
    public function __construct(
        private string $key,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }
}
