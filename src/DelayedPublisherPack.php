<?php
declare(strict_types=1);

namespace SWF;

use SWF\Interface\ProducerInterface;

/**
 * @internal
 */
final readonly class DelayedPublisherPack
{
    public function __construct(
        public ProducerInterface $producer,
        public AbstractMessage $message,
    ) {
    }
}
