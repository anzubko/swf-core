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
        public readonly array $declarations,
        public readonly DatabaserException $exception,
        public readonly int $retry,
    ) {
    }
}
