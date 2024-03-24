<?php declare(strict_types=1);

namespace SWF;

use ValueError;
use function count;

final class ImprovedCurl
{
    private ?string $body = null;

    /**
     * @var string[]
     */
    private array $headers = [];

    /**
     * @var mixed[]
     */
    private array $info;

    /**
     * Do Curl request with optional conversion to utf-8.
     *
     * @param mixed[] $options
     */
    public function __construct(array $options, bool $toUtf8 = false)
    {
        $options[CURLOPT_RETURNTRANSFER] = true;
        $options[CURLOPT_HEADER] = true;
        $options[CURLOPT_SSL_VERIFYPEER] ??= false;
        $options[CURLOPT_FOLLOWLOCATION] ??= true;

        $curl = curl_init();

        curl_setopt_array($curl, $options);

        $response = curl_exec($curl);

        $this->info = (array) curl_getinfo($curl);

        curl_close($curl);

        if (false === $response) {
            return;
        }

        $this->body = substr((string) $response, $this->info['header_size']);

        $rawHeaders = explode("\r\n\r\n", rtrim(substr((string) $response, 0, $this->info['header_size'])));

        unset($response);

        if (count($rawHeaders) > 0) {
            $this->headers = preg_split('/\r\n/', end($rawHeaders), flags: PREG_SPLIT_NO_EMPTY) ?: [];
        }

        unset($rawHeaders);

        if ($toUtf8 && !mb_check_encoding($this->body)) {
            if (preg_match('/charset\s*=\s*([a-z\d\-#]+)/i', $this->info['content_type'], $M)) {
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
     *
     * @return mixed[]
     */
    public function getInfo(): array
    {
        return $this->info;
    }
}
