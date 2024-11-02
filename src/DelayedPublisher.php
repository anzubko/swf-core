<?php
declare(strict_types=1);

namespace SWF;

use LogicException;
use ReflectionAttribute;
use ReflectionClass;
use SWF\Attribute\AsListener;
use SWF\Attribute\SetProducer;
use SWF\Event\AfterCommandEvent;
use SWF\Event\AfterControllerEvent;
use SWF\Event\TransactionBeginEvent;
use SWF\Event\TransactionCommitEvent;
use SWF\Event\TransactionRollbackEvent;
use Throwable;

final class DelayedPublisher
{
    /**
     * @var DelayedPublisherPack[]
     */
    private array $packs = [];

    /**
     * @var DelayedPublisherPack[]
     */
    private array $deferredPacks = [];

    private bool $inTrans = false;

    /**
     * Adds message to local queue.
     *
     * @throws LogicException
     */
    public function add(AbstractMessage $message): self
    {
        foreach ((new ReflectionClass($message))->getAttributes(SetProducer::class, ReflectionAttribute::IS_INSTANCEOF) as $rAttribute) {
            /** @var SetProducer $instance */
            $instance = $rAttribute->newInstance();

            $pack = new DelayedPublisherPack($instance->producer, $message);

            if ($this->inTrans) {
                $this->deferredPacks[] = $pack;
            } else {
                $this->packs[] = $pack;
            }

            return $this;
        }

        throw new LogicException(sprintf('Use SetProducer attribute to set producer for message %s', $message::class));
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
            $this->packs += $this->deferredPacks;
            $this->deferredPacks = [];
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
            $this->deferredPacks = [];
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
    public function publish(bool $silent = false): void
    {
        while ($this->packs) {
            $pack = array_shift($this->packs);

            if ($silent) {
                try {
                    $pack->producer->publish($pack->message);
                } catch (Throwable $e) {
                    i(CommonLogger::class)->error($e);
                }
            } else {
                $pack->producer->publish($pack->message);
            }
        }
    }

    /**
     * @phpstan-ignore method.unused
     */
    #[AsListener(priority: PHP_FLOAT_MIN, persistent: true)]
    private function autoPublish(AfterCommandEvent | AfterControllerEvent $event): void
    {
        $this->publish(true);
    }
}
