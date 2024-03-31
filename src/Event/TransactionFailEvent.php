<?php declare(strict_types=1);

namespace SWF\Event;

use Psr\Log\LogLevel;
use SWF\AbstractEvent;
use SWF\Exception\DatabaserException;

/**
 * Emits on transaction fails.
 */
class TransactionFailEvent extends AbstractEvent
{
    public function __construct(
        private readonly string $level,
        private readonly DatabaserException $exception,
        private readonly int $retry,
    ) {
    }

    /**
     * @see LogLevel
     */
    public function getLevel(): string
    {
        return $this->level;
    }

    public function getException(): DatabaserException
    {
        return $this->exception;
    }

    public function getRetry(): int
    {
        return $this->retry;
    }
}
