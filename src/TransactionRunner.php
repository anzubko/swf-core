<?php
declare(strict_types=1);

namespace SWF;

use SWF\Event\TransactionBeginEvent;
use SWF\Event\TransactionCommitEvent;
use SWF\Event\TransactionRetryEvent;
use SWF\Event\TransactionRollbackEvent;
use SWF\Exception\DatabaserException;
use SWF\Interface\DatabaserInterface;
use Throwable;
use function count;
use function in_array;

final class TransactionRunner
{
    /**
     * @var TransactionDeclaration[] $declarations
     */
    private array $declarations = [];

    public function __construct(
        private readonly DatabaserInterface $defaultDb,
    ) {
    }

    public function with(DatabaserInterface $db, ?string $isolation = null, string ...$states): self
    {
        $this->declarations[] = new TransactionDeclaration($db, $isolation, $states);

        return $this;
    }

    /**
     * @throws DatabaserException
     * @throws Throwable
     */
    public function run(callable $body, int $retries = 3): void
    {
        $declarations = $this->declarations;
        if (count($declarations) === 0) {
            $declarations[] = new TransactionDeclaration($this->defaultDb);
        } else {
            $this->declarations = [];
        }

        for ($retry = 0; $retry <= $retries; $retry++) {
            try {
                foreach ($declarations as $declaration) {
                    $declaration->db->begin($declaration->isolation);
                }

                i(EventDispatcher::class)->dispatch(new TransactionBeginEvent());

                if ($body() === false) {
                    foreach ($declarations as $declaration) {
                        $declaration->db->rollback();
                    }

                    i(EventDispatcher::class)->dispatch(new TransactionRollbackEvent());
                } else {
                    foreach ($declarations as $declaration) {
                        $declaration->db->commit();
                    }

                    i(EventDispatcher::class)->dispatch(new TransactionCommitEvent());
                }

                return;
            } catch (Throwable $e) {
                foreach ($declarations as $declaration) {
                    $declaration->db->rollback();
                }

                i(EventDispatcher::class)->dispatch(new TransactionRollbackEvent());

                if ($e instanceof DatabaserException && $retry < $retries) {
                    foreach ($declarations as $declaration) {
                        if (in_array($e->getState(), $declaration->states, true)) {
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
