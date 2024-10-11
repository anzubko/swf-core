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
    public function __construct(
        private readonly string $level,
        private readonly string $complexMessage,
        private readonly ?Throwable $exception,
    ) {
    }

    /**
     * @see LogLevel
     */
    public function getLevel(): string
    {
        return $this->level;
    }

    public function getComplexMessage(): string
    {
        return $this->complexMessage;
    }

    public function getException(): ?Throwable
    {
        return $this->exception;
    }
}
