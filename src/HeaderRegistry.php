<?php declare(strict_types=1);

namespace SWF;

use DateTimeInterface;
use function array_key_exists;
use function count;

final class HeaderRegistry
{
    /**
     * @var string[][]
     */
    private array $headers = [];

    /**
     * Adds header values by key.
     *
     * @param string|string[] $values
     */
    public function add(string $key, string|array $values): self
    {
        $key = strtolower($key);
        foreach ((array) $values as $value) {
            $this->headers[$key][] = $value;
        }

        return $this;
    }

    /**
     * Gets header values by key.
     *
     * @return string[]
     */
    public function get(string $key): array
    {
        return $this->headers[strtolower($key)] ?? [];
    }

    /**
     * Gets all headers values by keys.
     *
     * @return string[][]
     */
    public function getAll(): array
    {
        return $this->headers;
    }

    /**
     * Gets all headers keys and values as formatted lines.
     *
     * @return string[]
     */
    public function getAllLines(): array
    {
        $lines = [];
        foreach ($this->headers as $key => $value) {
            $lines[] = sprintf('%s: %s', $key, implode(', ', $value));
        }

        return $lines;
    }

    /**
     * Checks header values by key for contains needle.
     */
    public function contains(string $key, string $needle): bool
    {
        $needle = strtolower($needle);
        foreach ($this->headers[strtolower($key)] as $value) {
            if (str_contains(strtolower($value), $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks header exists by key.
     */
    public function has(string $key): bool
    {
        return array_key_exists(strtolower($key), $this->headers);
    }

    /**
     * Checks header exists by all keys.
     */
    public function hasAll(string ...$keys): bool
    {
        foreach ($keys as $key) {
            if (!array_key_exists(strtolower($key), $this->headers)) {
                return false;
            }
        }

        return count($keys) > 0;
    }

    /**
     * Checks header exists by any keys.
     */
    public function hasAny(string ...$keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists(strtolower($key), $this->headers)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove header by key.
     */
    public function remove(string ...$keys): self
    {
        foreach ($keys as $key) {
            unset($this->headers[strtolower($key)]);
        }

        return $this;
    }

    /**
     * Remove all headers.
     */
    public function removeAll(): self
    {
        $this->headers = [];

        return $this;
    }

    /**
     * Sets header values by key.
     *
     * @param string|string[] $values
     */
    public function set(string $key, string|array $values, bool $overwrite = true): self
    {
        $key = strtolower($key);
        if (!$overwrite && array_key_exists($key, $this->headers)) {
            return $this;
        }

        $this->headers[$key] = (array) $values;

        return $this;
    }

    /**
     * Sets content type header.
     */
    public function setContentType(string $type, ?string $charset = null, bool $overwrite = true): self
    {
        if (!$overwrite && array_key_exists('content-type', $this->headers)) {
            return $this;
        }

        if (null === $charset) {
            $this->headers['content-type'] = [$type];
        } else {
            $this->headers['content-type'] = [sprintf('%s; charset=%s', $type, $charset)];
        }

        return $this;
    }

    /**
     * Sets last modified header.
     */
    public function setLastModified(DateTimeInterface|int $modified, bool $overwrite = true): self
    {
        if (!$overwrite && array_key_exists('last-modified', $this->headers)) {
            return $this;
        }

        if ($modified instanceof DateTimeInterface) {
            $modified = $modified->getTimestamp();
        }

        $this->headers['last-modified'] = [gmdate('D, d M Y H:i:s \G\M\T', $modified)];

        return $this;
    }

    /**
     * Sets expires header.
     */
    public function setExpires(DateTimeInterface|int $expires, bool $overwrite = true): self
    {
        if (!$overwrite && array_key_exists('expires', $this->headers)) {
            return $this;
        }

        if ($expires instanceof DateTimeInterface) {
            $expires = $expires->getTimestamp();
        }

        $this->headers['expires'] = [gmdate('D, d M Y H:i:s \G\M\T', $expires)];

        return $this;
    }

    /**
     * Sets etag header.
     */
    public function setETag(string $eTag, bool $weak = false, bool $overwrite = true): self
    {
        if (!$overwrite && array_key_exists('etag', $this->headers)) {
            return $this;
        }

        if (!str_starts_with($eTag, '"')) {
            $eTag = sprintf('"%s"', $eTag);
        }

        $this->headers['etag'] = [($weak ? 'W/' : '') . $eTag];

        return $this;
    }

    /**
     * Sets content disposition header.
     */
    public function setContentDisposition(string $disposition, ?string $filename = null, bool $overwrite = true): self
    {
        if (!$overwrite && array_key_exists('content-disposition', $this->headers)) {
            return $this;
        }

        if (null === $filename) {
            $this->headers['content-disposition'] = [$disposition];
        } else {
            $this->headers['content-disposition'] = [sprintf("%s; filename*=utf-8''%s", $disposition, rawurlencode($filename))];
        }

        return $this;
    }
}
