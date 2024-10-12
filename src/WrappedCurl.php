<?php
declare(strict_types=1);

namespace SWF;

use CurlHandle;
use ValueError;
use function count;

final class WrappedCurl
{
    private CurlHandle $curl;

    private ?string $body = null;

    /**
     * @var string[]
     */
    private array $headers = [];

    /**
     * Does Curl request with optional conversion to utf-8.
     *
     * @param mixed[] $options
     */
    public function __construct(array $options, bool $toUtf8 = false)
    {
        $options[CURLOPT_RETURNTRANSFER] = true;
        $options[CURLOPT_HEADER] = true;
        $options[CURLOPT_SSL_VERIFYPEER] ??= false;
        $options[CURLOPT_FOLLOWLOCATION] ??= true;

        $this->curl = curl_init();

        curl_setopt_array($this->curl, $options);

        $response = curl_exec($this->curl);
        if (false === $response) {
            return;
        }

        $headerSize = (int) $this->getInfo(CURLINFO_HEADER_SIZE);

        $this->body = substr((string) $response, $headerSize);

        $rawHeaders = explode("\r\n\r\n", rtrim(substr((string) $response, 0, $headerSize)));

        unset($response);

        if (count($rawHeaders) > 0) {
            $this->headers = preg_split('/\r\n/', end($rawHeaders), flags: PREG_SPLIT_NO_EMPTY) ?: [];
        }

        unset($rawHeaders);

        if ($toUtf8 && !mb_check_encoding($this->body)) {
            if (preg_match('/charset\s*=\s*([a-z\d\-#]+)/i', (string) $this->getInfo(CURLINFO_CONTENT_TYPE), $M)) {
                try {
                    $this->body = mb_convert_encoding($this->body, 'utf-8', $M[1]);
                } catch (ValueError) {
                    $this->body = null;
                }
            } else {
                $this->body = null;
            }
        }
    }

    /**
     * Gets response body.
     */
    public function getBody(): ?string
    {
        return $this->body;
    }

    /**
     * Gets response headers.
     *
     * @return string[]
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Gets response info.
     */
    public function getInfo(int $option): mixed
    {
        return curl_getinfo($this->curl, $option);
    }
}
