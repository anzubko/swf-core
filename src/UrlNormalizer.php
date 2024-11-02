<?php
declare(strict_types=1);

namespace SWF;

use InvalidArgumentException;

final class UrlNormalizer
{
    private ?string $scheme = null;

    private ?string $host = null;

    private ?string $url = null;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(?string $url)
    {
        if ($url === null) {
            return;
        }

        $parsedUrl = parse_url($url);
        if (empty($parsedUrl) || !isset($parsedUrl['host'])) {
            throw new InvalidArgumentException('Incorrect URL');
        }

        $this->scheme = $parsedUrl['scheme'] ?? 'http';

        if (isset($parsedUrl['port'])) {
            $this->host = sprintf('%s:%s', $parsedUrl['host'], $parsedUrl['port']);
        } else {
            $this->host = $parsedUrl['host'];
        }

        $this->url = sprintf('%s://%s', $this->scheme, $this->host);
    }

    public function getScheme(): ?string
    {
        return $this->scheme;
    }

    public function getHost(): ?string
    {
        return $this->host;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }
}
