<?php
declare(strict_types=1);

namespace SWF\Interface;

use Throwable;

interface MessageDispatcherInterface
{
    /**
     * Provides all relevant consumers with a message to process.
     *
     * @template T of object
     *
     * @param T $message
     *
     * @return T
     *
     * @throws Throwable
     */
    public function dispatch(mixed $message);
}
