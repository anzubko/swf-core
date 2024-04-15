<?php declare(strict_types=1);

namespace SWF;

use SWF\Event\TransactionCommitEvent;
use SWF\Event\TransactionFailEvent;
use SWF\Event\TransactionRollbackEvent;
use SWF\Exception\DatabaserException;
use SWF\Interface\DatabaserInterface;
use Throwable;
use function in_array;

final class TransactionRunner
{
    /**
     * Base method for processing transaction.
     *
     * @param string[] $retryAt
     *
     * @throws DatabaserException
     * @throws Throwable
     */
    public function run(DatabaserInterface $db, callable $body, ?string $isolation, array $retryAt, int $retries = 7): void
    {
        while (--$retries >= 0) {
            try {
                InstanceHolder::get(ListenerProvider::class)->removeListenersByType([
                    TransactionCommitEvent::class,
                    TransactionRollbackEvent::class,
                ]);

                $db->begin($isolation);

                if (false !== $body()) {
                    $db->commit();

                    InstanceHolder::get(EventDispatcher::class)->dispatch(new TransactionCommitEvent());
                } else {
                    $db->rollback();

                    InstanceHolder::get(EventDispatcher::class)->dispatch(new TransactionRollbackEvent());
                }

                return;
            } catch (Throwable $e) {
                try {
                    $db->rollback();
                } catch (DatabaserException) {
                }

                if (!$e instanceof DatabaserException) {
                    throw $e;
                }

                InstanceHolder::get(EventDispatcher::class)->dispatch(new TransactionFailEvent($e, $retries));

                if ($retries === 0 || !in_array($e->getSqlState(), $retryAt, true)) {
                    throw $e;
                }
            }
        }
    }
}
