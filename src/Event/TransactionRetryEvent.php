<?php
declare(strict_types=1);

namespace SWF\Event;

use SWF\AbstractEvent;
use SWF\Exception\DatabaserException;
use SWF\TransactionDeclaration;

/**
 * Emits on transaction retries.
 */
class TransactionRetryEvent extends AbstractEvent
{
    /**
     * @param TransactionDeclaration[] $declarations
     */
    public function __construct(
        private readonly array $declarations,
        private readonly DatabaserException $exception,
        private readonly int $retry,
    ) {
    }

    /**
     * @return TransactionDeclaration[]
     */
    public function getDeclarations(): array
    {
        return $this->declarations;
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
