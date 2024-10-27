<?php
declare(strict_types=1);

namespace SWF;

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
     * @var array<int, AbstractNotify>
     */
    private array $notifies = [];

    /**
     * @var array<int, AbstractNotify>
     */
    private array $deferredNotifies = [];

    private int $id = 1;

    private bool $inTrans = false;

    /**
     * Adds notify to local queue and returns identifier.
     */
    public function add(AbstractNotify $notify): int
    {
        if ($this->inTrans) {
            $this->deferredNotifies[$this->id] = $notify;
        } else {
            $this->notifies[$this->id] = $notify;
        }

        return $this->id++;
    }

    /**
     * Removes notifies from local queue.
     *
     * @param int[] $ids
     */
    public function remove(array $ids): self
    {
        foreach ($ids as $id) {
            unset($this->notifies[$id], $this->deferredNotifies[$id]);
        }

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
            $this->notifies += $this->deferredNotifies;
            $this->deferredNotifies = [];
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
            $this->deferredNotifies = [];
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
     * Sends notifies.
     */
    public function send(): void
    {
        while ($this->notifies) {
            try {
                array_shift($this->notifies)->send();
            } catch (Throwable $e) {
                i(CommonLogger::class)->error($e);
            }
        }
    }

    /**
     * @phpstan-ignore method.unused
     */
    #[AsListener(priority: PHP_FLOAT_MIN, persistent: true)]
    private function autoSend(AfterCommandEvent | AfterControllerEvent $event): void
    {
        $this->send();
    }
}
