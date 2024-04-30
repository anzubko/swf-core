<?php declare(strict_types=1);

namespace SWF\Event;

use SWF\AbstractEvent;
use SWF\HeaderRegistry;

/**
 * Emits before response sending.
 */
class BeforeResponseSendEvent extends AbstractEvent
{
    /**
     * @param string|resource $body
     */
    public function __construct(
        private readonly HeaderRegistry $headers,
        private mixed $body,
    ) {
    }

    public function getHeaders(): HeaderRegistry
    {
        return $this->headers;
    }

    /**
     * @return string|resource
     */
    public function getBody(): mixed
    {
        return $this->body;
    }

    /**
     * @param string|resource $body
     */
    public function setBody(mixed $body): void
    {
        $this->body = $body;
    }
}
