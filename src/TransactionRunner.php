<?php declare(strict_types=1);

namespace SWF;

use SWF\Event\TransactionFailEvent;
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
    public function run(DatabaserInterface $db, callable $body, ?string $isolation, array $retryAt, int $retries = 3): void
    {
        while (--$retries >= 0) {
            try {
                $db->begin($isolation);

                if (false !== $body()) {
                    $db->commit();
                } else {
                    $db->rollback();
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
