<?php
declare(strict_types=1);

namespace SWF\Event;

use SWF\AbstractEvent;
use SWF\HeaderRegistry;

/**
 * Emits before response sending.
 */
class ResponseEvent extends AbstractEvent
{
    /**
     * @param string|resource $body
     */
    public function __construct(
        public readonly HeaderRegistry $headers,
        public mixed $body,
    ) {
    }
}
