<?php
declare(strict_types=1);

namespace SWF\Attribute;

use Attribute;
use SWF\Interface\ProducerInterface;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class SetProducer
{
    public function __construct(
        public ProducerInterface $producer,
    ) {
    }
}
