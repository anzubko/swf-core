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
        public readonly DatabaserException $exception,
        public readonly int $retries,
    ) {
    }
}
