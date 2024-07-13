<?php declare(strict_types=1);

namespace SWF;

use ReflectionClass;

final class ActionClasses
{
    /**
     * @param array<ReflectionClass<object>> $list
     */
    public function __construct(
        public array $list = [],
    ) {
    }
}
