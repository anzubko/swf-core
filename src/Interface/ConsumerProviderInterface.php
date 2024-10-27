<?php
declare(strict_types=1);

namespace SWF\Interface;

use ReflectionException;

interface ConsumerProviderInterface
{
    /**
     * Returns consumers that are applicable to that message.
     *
     * @return iterable<callable>
     *
     * @throws ReflectionException
     */
    public function getConsumersForMessage(object $message) : iterable;
}
