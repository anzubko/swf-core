<?php
declare(strict_types=1);

namespace SWF\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class GetEnv
{
    /**
     * Overrides configuration property by some ENV value.
     *
     * @param string $key Key of some ENV parameter.
     */
    public function __construct(
        public string $key,
    ) {
    }
}
