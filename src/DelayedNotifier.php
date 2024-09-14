<?php declare(strict_types=1);

namespace SWF;

use LogicException;
use RuntimeException;
use SWF\Event\ShutdownEvent;
use SWF\Event\TransactionCommitEvent;
use SWF\Interface\DatabaserInterface;
use Throwable;

final class DelayedNotifier
{
    /**
     * @var AbstractNotify[]
     */
    private array $notifies = [];

    /**
     * @throws LogicException
     * @throws RuntimeException
     */
    public function __construct()
    {
        i(ListenerProvider::class)->addListener(
            callback: function (ShutdownEvent $event): void {
                register_shutdown_function($this->sendAll(...));
            },
            persistent: true,
        );
    }

    /**
     * Adds notify to queue only if current transaction successful commit, or it's called outside of transaction.
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    public function add(AbstractNotify $notify): void
    {
        foreach (InstanceStorage::$instances as $instance) {
            if ($instance instanceof DatabaserInterface && $instance->isInTrans()) {
                i(ListenerProvider::class)->addListener(
                    callback: function (TransactionCommitEvent $event) use ($notify): void {
                        $this->add($notify);
                    },
                    disposable: true,
                );
                return;
            }
        }

        $this->notifies[] = $notify;
    }

    /**
     * Calls send() method at all notifies and remove them from queue.
     */
    public function sendAll(): void
    {
        while ($this->notifies) {
            try {
                array_shift($this->notifies)->send();
            } catch (Throwable $e) {
                i(CommonLogger::class)->error($e);
            }
        }
    }
}
