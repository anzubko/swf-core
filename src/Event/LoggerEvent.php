<?php declare(strict_types=1);

namespace SWF\Event;

use Psr\Log\LogLevel;
use SWF\AbstractEvent;

/**
 * Emits at regular logger calls (not exceptions, not custom destinations)
 * and only if file and line trace are enabled and detected.
 */
class LoggerEvent extends AbstractEvent
{
    /**
     * @param mixed[] $context
     */
    public function __construct(
        private readonly string $level,
        private readonly string $message,
        private readonly string $file,
        private readonly int $line,
        private readonly array $context,
    ) {
    }

    /**
     * @see LogLevel
     */
    public function getLevel(): string
    {
        return $this->level;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * @return mixed[]
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
