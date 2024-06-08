<?php declare(strict_types=1);

namespace SWF\Event;

use SWF\AbstractEvent;

/**
 * Emits after current(!) transaction is rolled back.
 *
 * Listener will be ignored if provided outside of transaction.
 */
class TransactionRollbackEvent extends AbstractEvent
{
}
