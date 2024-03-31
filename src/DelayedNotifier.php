<?php declare(strict_types=1);

namespace SWF;

use Psr\Log\LogLevel;
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

    private static self $instance;

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        ListenerProvider::getInstance()->addListener(
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
        foreach ((array) (new ReflectionClass(AbstractBase::class))->getStaticPropertyValue('shared') as $class) {
            if ($class instanceof DatabaserInterface && $class->isInTrans()) {
                ListenerProvider::getInstance()->addListener(
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
                CommonLogger::getInstance()->log(LogLevel::ERROR, $e);
            }
        }
    }
}
