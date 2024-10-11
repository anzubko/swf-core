<?php
declare(strict_types=1);

namespace SWF\Event;

use SWF\AbstractEvent;

/**
 * Emits at response error (only if headers not sent yet).
 */
class HttpErrorEvent extends AbstractEvent
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
