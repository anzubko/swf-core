<?php
declare(strict_types=1);

namespace SWF;

abstract class AbstractNotify
{
    /**
     * This method will be called after controller, command and all listeners finish works.
     */
    abstract public function send(): void;
}
