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
     * Returns error or info level.
     *
     * @see LogLevel
     */
    public function getLevel(): string
    {
        return $this->level;
    }

    /**
     * Returns exception with sqlstate.
     */
    public function getException(): DatabaserException
    {
        return $this->exception;
    }

    /**
     * Returns current retry number.
     */
    public function getRetry(): int
    {
        return $this->retry;
    }
}
