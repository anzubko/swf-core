<?php declare(strict_types=1);

namespace SWF;

use SWF\Event\TransactionRetryEvent;
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
