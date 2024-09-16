<?php declare(strict_types=1);

namespace SWF;

use ReflectionException;
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
     * @throws ReflectionException
     * @throws Throwable
     */
    public function run(DatabaserInterface $db, callable $body, ?string $isolation, array $retryAt, int $retries = 3): void
    {
        while (--$retries >= 0) {
            try {
                i(ListenerProvider::class)->removeListenersByType([
                    TransactionCommitEvent::class,
                    TransactionRollbackEvent::class,
                ]);

                $db->begin($isolation);

                if (false !== $body()) {
                    $db->commit();

                    i(EventDispatcher::class)->dispatch(new TransactionCommitEvent());
                } else {
                    $db->rollback();

                    i(EventDispatcher::class)->dispatch(new TransactionRollbackEvent());
                }

                return;
            } catch (Throwable $e) {
                try {
                    $db->rollback(true);
                } catch (DatabaserException) {
                }

                if (!$e instanceof DatabaserException) {
                    throw $e;
                }

                i(EventDispatcher::class)->dispatch(new TransactionFailEvent($e, $retries));

                if (0 === $retries || !in_array($e->getSqlState(), $retryAt, true)) {
                    throw $e;
                }
            }
        }
    }
}
