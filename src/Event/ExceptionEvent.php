<?php declare(strict_types=1);

namespace SWF\Event;

use SWF\AbstractEvent;
use Throwable;

/**
 * Emits at exceptions.
 */
class ExceptionEvent extends AbstractEvent
{
    public function __construct(
        private readonly Throwable $exception,
    ) {
    }

    public function getException(): Throwable
    {
        return $this->exception;
    }
}
