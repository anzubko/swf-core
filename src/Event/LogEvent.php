<?php
declare(strict_types=1);

namespace SWF\Event;

use Psr\Log\LogLevel;
use SWF\AbstractEvent;
use Throwable;

/**
 * Emits at logger calls.
 */
class LogEvent extends AbstractEvent
{
    /**
     * @param string $level {@see LogLevel}
     */
    public function __construct(
        public readonly string $level,
        public readonly string $complexMessage,
        public readonly ?Throwable $exception,
    ) {
    }
}
