<?php
declare(strict_types=1);

namespace SWF\Attribute;

use Attribute;
use SWF\Interface\ProducerInterface;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class SetProducer
{
    public function __construct(
        private ProducerInterface $producer,
    ) {
    }

    public function getProducer(): ProducerInterface
    {
        return $this->producer;
    }
}
