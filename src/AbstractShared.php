<?php declare(strict_types=1);

namespace SWF;

abstract class AbstractShared
{
    /**
     * @internal
     */
    protected static function getInstance(): object
    {
        return new static(); // @phpstan-ignore-line
    }
}
