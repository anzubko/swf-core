<?php
declare(strict_types=1);

namespace SWF\Interface;

use SWF\AbstractMessage;

interface ProducerInterface
{
    public function publish(AbstractMessage $message): void;
}
