<?php
declare(strict_types=1);

namespace SWF;

use InvalidArgumentException;
use LogicException;
use SWF\Attribute\AsListener;
use SWF\Event\AfterCommandEvent;
use SWF\Event\AfterControllerEvent;
use SWF\Event\TransactionBeginEvent;
use SWF\Event\TransactionCommitEvent;
use SWF\Event\TransactionRollbackEvent;
use Throwable;

final class DelayedNotifier
{
    /**
     * @var AbstractNotify[]
     */
    private array $primaryQueue = [];

    /**
     * @var AbstractNotify[]
     */
    private array $secondaryQueue = [];

    private int $id = 1;

    private bool $inTrans = false;

    /**
     * @throws InvalidArgumentException
     * @throws LogicException
     */
    public function __construct()
    {
        i(ListenerProvider::class)->addListener(
            callback: function (AfterCommandEvent | AfterControllerEvent $event): void {
                $this->sendAll();
            },
            priority: PHP_FLOAT_MIN,
            persistent: true,
        );
    }

    /**
     * Adds notify to queue and returns notify identifier.
     */
    public function add(AbstractNotify $notify): int
    {
        if ($this->inTrans) {
            $this->secondaryQueue[$this->id] = $notify;
        } else {
            $this->primaryQueue[$this->id] = $notify;
        }

        return $this->id++;
    }

    /**
     * Removes notify from queue.
     */
    public function remove(int $id): self
    {
        unset($this->primaryQueue[$id], $this->secondaryQueue[$id]);

        return $this;
    }

    /**
     * Begins transaction.
     */
    public function begin(): self
    {
        $this->inTrans = true;

        return $this;
    }

    /**
     * @phpstan-ignore method.unused
     */
    #[AsListener(persistent: true)]
    private function syncBegins(TransactionBeginEvent $event): void
    {
        $this->begin();
    }

    /**
     * Commits transaction.
     */
    public function commit(): self
    {
        if ($this->inTrans) {
            $this->inTrans = false;
            $this->primaryQueue += $this->secondaryQueue;
            $this->secondaryQueue = [];
        }

        return $this;
    }

    /**
     * @phpstan-ignore method.unused
     */
    #[AsListener(persistent: true)]
    private function syncCommits(TransactionCommitEvent $event): void
    {
        $this->commit();
    }

    /**
     * Rollbacks transaction.
     */
    public function rollback(): self
    {
        if ($this->inTrans) {
            $this->inTrans = false;
            $this->secondaryQueue = [];
        }

        return $this;
    }

    /**
     * @phpstan-ignore method.unused
     */
    #[AsListener(persistent: true)]
    private function syncRollbacks(TransactionRollbackEvent $event): void
    {
        $this->rollback();
    }

    /**
     * Sends all notifies.
     */
    public function sendAll(): void
    {
        while ($this->primaryQueue) {
            try {
                array_shift($this->primaryQueue)->send();
            } catch (Throwable $e) {
                i(CommonLogger::class)->error($e);
            }
        }
    }
}
