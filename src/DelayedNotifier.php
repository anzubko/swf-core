<?php
declare(strict_types=1);

namespace SWF;

use InvalidArgumentException;
use LogicException;
use SWF\Event\AfterCommandEvent;
use SWF\Event\AfterControllerEvent;
use Throwable;

final class DelayedNotifier
{
    /**
     * @var AbstractNotify[]
     */
    private array $notifies = [];

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
     * Adds notify to queue.
     */
    public function add(AbstractNotify $notify): void
    {
        $this->notifies[] = $notify;
    }

    /**
     * Sends all notifies.
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
