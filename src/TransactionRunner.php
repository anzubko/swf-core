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
    private static self $instance;

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
    }

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
                ListenerProvider::getInstance()->removeListenersByType([
                    TransactionCommitEvent::class,
                    TransactionRollbackEvent::class,
                ]);

                $db->begin($isolation);

                if (false !== $body()) {
                    $db->commit();

                    EventDispatcher::getInstance()->dispatch(new TransactionCommitEvent());
                } else {
                    $db->rollback();

                    EventDispatcher::getInstance()->dispatch(new TransactionRollbackEvent());
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

                EventDispatcher::getInstance()->dispatch(new TransactionFailEvent($e, $retries));

                if ($retries === 0 || !in_array($e->getSqlState(), $retryAt, true)) {
                    throw $e;
                }
            }
        }
    }
}
