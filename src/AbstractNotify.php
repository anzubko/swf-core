<?php declare(strict_types=1);

namespace SWF;

abstract class AbstractNotify
{
    /**
     * This method will be called after browser disconnect as last shutdown function.
     */
    abstract public function send(): void;
}
