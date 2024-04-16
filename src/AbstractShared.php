<?php declare(strict_types=1);

namespace SWF;

abstract class AbstractShared
{
    /**
     * @internal
     */
    protected function getInstance(): object
    {
        return $this;
    }
}
