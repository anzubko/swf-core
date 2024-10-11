<?php declare(strict_types=1);

namespace SWF;

use SWF\Event\TransactionRetryEvent;
use SWF\Exception\DatabaserException;
use Throwable;
use function in_array;

final class TransactionRunner
{
    /**
     * @param TransactionDeclaration[] $declarations
     *
     * @throws DatabaserException
     * @throws Throwable
     */
    public function run(callable $body, array $declarations, int $retries = 3): void
    {
        for ($retry = 0; $retry <= $retries; $retry++) {
            try {
                foreach ($declarations as $declaration) {
                    $declaration->getDb()->begin($declaration->getIsolation());
                }

                if (false === $body()) {
                    foreach ($declarations as $declaration) {
                        $declaration->getDb()->rollback();
                    }
                } else {
                    foreach ($declarations as $declaration) {
                        $declaration->getDb()->commit();
                    }
                }

                return;
            } catch (Throwable $e) {
                foreach ($declarations as $declaration) {
                    try {
                        $declaration->getDb()->rollback(true);
                    } catch (DatabaserException) {
                    }
                }

                if ($e instanceof DatabaserException && $retry < $retries) {
                    foreach ($declarations as $declaration) {
                        if (in_array($e->getState(), $declaration->getStates(), true)) {
                            i(EventDispatcher::class)->dispatch(new TransactionRetryEvent($declarations, $e, $retry + 1));

                            continue 2;
                        }
                    }
                }

                throw $e;
            }
        }
    }
}
