<?php
declare(strict_types=1);

namespace SWF;

use SWF\Interface\MessageDispatcherInterface;
use SWF\Interface\StoppableMessageInterface;

final class MessageDispatcher implements MessageDispatcherInterface
{
    /**
     * @inheritDoc
     */
    public function dispatch(mixed $message)
    {
        foreach (i(ConsumerProvider::class)->getConsumersForMessage($message) as $consumer) {
            if ($message instanceof StoppableMessageInterface && $message->isPropagationStopped()) {
                return $message;
            }

            $consumer($message);
        }

        return $message;
    }
}
