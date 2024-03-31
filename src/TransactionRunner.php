<?php declare(strict_types=1);

namespace SWF;

use Psr\Log\LogLevel;
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
    public function run(DatabaserInterface $db, callable $body, ?string $isolation, array $retryAt): void
    {
        for ($retry = 1, $retries = ConfigHolder::get()->transactionRetries; $retry <= $retries; $retry++) {
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

                if (in_array($e->getSqlState(), $retryAt, true) && $retry < $retries) {
                    EventDispatcher::getInstance()->dispatch(new TransactionFailEvent(LogLevel::INFO, $e, $retry));
                } else {
                    EventDispatcher::getInstance()->dispatch(new TransactionFailEvent(LogLevel::ERROR, $e, $retry));

                    throw $e;
                }
            }
        }
    }
}
