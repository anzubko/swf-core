<?php declare(strict_types=1);

namespace SWF\Event;

use SWF\AbstractEvent;

/**
 * Emits after current(!) transaction is successfully committed.
 *
 * Listener will be ignored if provided outside of transaction.
 */
class TransactionCommitEvent extends AbstractEvent
{
}
