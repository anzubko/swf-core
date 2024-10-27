<?php
declare(strict_types=1);

namespace SWF;

use SWF\Interface\StoppableMessageInterface;

abstract class AbstractMessage implements StoppableMessageInterface
{
    private bool $propagationStopped = false;

    /**
     * @inheritDoc
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * @inheritDoc
     */
    public function stopPropagation(): static
    {
        $this->propagationStopped = true;

        return $this;
    }
}
