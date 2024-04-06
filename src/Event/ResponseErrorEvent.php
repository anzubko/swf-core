<?php declare(strict_types=1);

namespace SWF\Event;

use Psr\Log\LogLevel;
use SWF\AbstractEvent;
use Throwable;

/**
 * Emits at response error (only if headers not sent yet).
 */
class ResponseErrorEvent extends AbstractEvent
{
    public function __construct(
        private readonly int $code,
    ) {
    }

    public function getCode(): int
    {
        return $this->code;
    }
}
