<?php declare(strict_types=1);

namespace SWF;

abstract class AbstractShared extends AbstractBase
{
    /**
     * @internal
     */
    protected function getInstance(): object
    {
        return $this;
    }
}
