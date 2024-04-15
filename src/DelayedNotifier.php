<?php declare(strict_types=1);

namespace SWF;

use ReflectionClass;
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

    public function __construct()
    {
        InstanceHolder::get(ListenerProvider::class)->addListener(
            function (ShutdownEvent $event) {
                register_shutdown_function($this->sendAll(...));
            },
            persistent: true,
        );
    }

    /**
     * Adds notify to queue only if current transaction successful commit, or it's called outside of transaction.
     */
    public function add(AbstractNotify $notify): void
    {
        $sharedClasses = (array) (new ReflectionClass(AbstractBase::class))->getStaticPropertyValue('shared');
        foreach ($sharedClasses as $class) {
            if ($class instanceof DatabaserInterface && $class->isInTrans()) {
                InstanceHolder::get(ListenerProvider::class)->addListener(
                    function (TransactionCommitEvent $event) use ($notify) {
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
                InstanceHolder::get(CommonLogger::class)->error($e);
            }
        }
    }
}
