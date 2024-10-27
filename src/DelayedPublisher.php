<?php
declare(strict_types=1);

namespace SWF;

use SWF\Attribute\AsListener;
use SWF\Event\AfterCommandEvent;
use SWF\Event\AfterControllerEvent;
use SWF\Event\TransactionBeginEvent;
use SWF\Event\TransactionCommitEvent;
use SWF\Event\TransactionRollbackEvent;

final class DelayedPublisher
{
    /**
     * @var AbstractMessage[]
     */
    private array $messages = [];

    /**
     * @var AbstractMessage[]
     */
    private array $deferredMessages = [];

    private bool $inTrans = false;

    /**
     * Adds message to local queue.
     */
    public function add(AbstractMessage $message): self
    {
        if ($this->inTrans) {
            $this->deferredMessages[] = $message;
        } else {
            $this->messages[] = $message;
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
            $this->messages += $this->deferredMessages;
            $this->deferredMessages = [];
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
            $this->deferredMessages = [];
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
     * Publish messages.
     */
    public function publish(): void
    {
    }

    /**
     * @phpstan-ignore method.unused
     */
    #[AsListener(priority: PHP_FLOAT_MIN, persistent: true)]
    private function autoPublish(AfterCommandEvent | AfterControllerEvent $event): void
    {
        $this->publish();
    }
}
