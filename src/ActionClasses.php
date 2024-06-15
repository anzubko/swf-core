<?php declare(strict_types=1);

namespace SWF;

final class ActionClasses
{
    /**
     * @param mixed[] $list
     */
    public function __construct(
        public array $list = [],
    ) {
    }
}
