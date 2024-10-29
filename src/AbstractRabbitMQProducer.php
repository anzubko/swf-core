<?php
declare(strict_types=1);

namespace SWF;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use SWF\Interface\ProducerInterface;

abstract class AbstractRabbitMQProducer implements ProducerInterface
{
    public function __construct(

    ) {
    }

    abstract protected function getConnection(): AMQPStreamConnection;
}
