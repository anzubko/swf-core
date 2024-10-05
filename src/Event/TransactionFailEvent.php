<?php declare(strict_types=1);

namespace SWF\Event;

use SWF\AbstractEvent;
use SWF\Exception\DatabaserException;

/**
 * Emits on transaction fails.
 */
class TransactionFailEvent extends AbstractEvent
{
    public function __construct(
        private readonly DatabaserException $exception,
        private readonly int $retries,
    ) {
    }

    public function getException(): DatabaserException
    {
        return $this->exception;
    }

    public function getRetries(): int
    {
        return $this->retries;
    }
}
