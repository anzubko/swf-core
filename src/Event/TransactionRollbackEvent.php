<?php declare(strict_types=1);

namespace SWF\Event;

use SWF\AbstractEvent;

/**
 * Emits after transaction is rolled back.
 */
class TransactionRollbackEvent extends AbstractEvent
{
}
