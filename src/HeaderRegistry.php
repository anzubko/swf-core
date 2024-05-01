<?php declare(strict_types=1);

namespace SWF;

use DateInterval;
use DateTime;
use DateTimeInterface;
use function array_key_exists;
use function is_int;
use function is_numeric;

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
    public function setLastModified(DateTimeInterface|string|int $modified, bool $overwrite = true): self
    {
        if (!$overwrite && array_key_exists('last-modified', $this->headers)) {
            return $this;
        }

        $this->headers['last-modified'] = [$this->normalizeDatetime($modified)];

        return $this;
    }

    /**
     * Sets expires header.
     */
    public function setExpires(DateTimeInterface|string|int $expires, bool $overwrite = true): self
    {
        if (!$overwrite && array_key_exists('expires', $this->headers)) {
            return $this;
        }

        $this->headers['expires'] = [$this->normalizeDatetime($expires)];

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

    /**
     * Sets cache control header.
     *
     * @param mixed[] $options
     */
    public function setCacheControl(array $options = [], bool $overwrite = true): self
    {
        if (!$overwrite && array_key_exists('cache-control', $this->headers)) {
            return $this;
        }

        $this->headers['cache-control'] = [];

        return $this->addCacheControl($options);
    }

    /**
     * Adds cache control header.
     *
     * @param mixed[] $options
     */
    public function addCacheControl(array $options = []): self
    {
        $options = array_change_key_case($options);

        foreach (['max-age', 's-maxage', 'max-stale', 'min-fresh', 'stale-while-revalidate', 'stale-if-error'] as $field) {
            if (array_key_exists($field, $options)) {
                $options[$field] = $this->normalizeInterval($options[$field]);
            }
        }

        foreach ($options as $key => $oValue) {
            if (is_int($key)) {
                $this->headers['cache-control'][] = $oValue;
            } else {
                $this->headers['cache-control'][] = sprintf('%s=%s', $key, $oValue);
            }
        }

        return $this;
    }

    /**
     * Adds cookie header.
     *
     * @param mixed[] $options
     */
    public function addCookie(string $name, ?string $value, array $options = []): self
    {
        $options = array_change_key_case($options);

        $options['path'] ??= '/';

        $chunks = [];
        if (null === $value) {
            $chunks[] = sprintf('%s=deleted', $name);

            $options['max-age'] = 0;
        } elseif ($options['raw'] ?? false) {
            $chunks[] = sprintf('%s=%s', $name, rawurlencode($value));
        } else {
            $chunks[] = sprintf('%s=%s', $name, $value);
        }

        if (array_key_exists('max-age', $options)) {
            $options['max-age'] = $this->normalizeInterval($options['max-age']);
        }

        if (array_key_exists('expires', $options)) {
            $options['expires'] = $this->normalizeDatetime($options['expires']);
        }

        foreach ($options as $key => $oValue) {
            if (is_int($key)) {
                $chunks[] = $oValue;
            } else {
                $chunks[] = sprintf('%s=%s', $key, $oValue);
            }
        }

        $this->headers['set-cookie'][] = implode('; ', $chunks);

        return $this;
    }

    private function normalizeInterval(mixed $value): int
    {
        if ($value instanceof DateInterval) {
            return (new DateTime())->setTimestamp(0)->add($value)->getTimestamp();
        }

        return (int) $value;
    }

    private function normalizeDatetime(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            $value = $value->getTimestamp();
        } elseif (!is_numeric($value)) {
            $value = strtotime($value);
        }

        return gmdate('D, d M Y H:i:s T', (int) $value);
    }
}
