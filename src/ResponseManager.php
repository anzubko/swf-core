<?php declare(strict_types=1);

namespace SWF;

use ReflectionException;
use SWF\Event\ResponseEvent;
use SWF\Event\HttpErrorEvent;
use SWF\Exception\SilentException;
use Throwable;
use function is_resource;
use function is_string;

final class ResponseManager
{
    private HeaderRegistry $headers;

    /**
     * Returns headers registry.
     */
    public function headers(): HeaderRegistry
    {
        return $this->headers ??= new HeaderRegistry();
    }

    /**
     * Sends response.
     *
     * @param string|resource $body
     *
     * @throws ReflectionException
     */
    public function send(mixed $body, int $code = 200): void
    {
        $body = i(EventDispatcher::class)->dispatch(new ResponseEvent($this->headers, $body))->getBody();

        http_response_code($code);

        foreach (self::headers()->getAllLines() as $line) {
            header($line);
        }

        if (is_resource($body)) {
            fpassthru($body);
        } elseif (is_string($body)) {
            echo $body;
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    /**
     * Redirects to specified url.
     */
    public function redirect(string $url, int $code = 302): void
    {
        header(sprintf('Location: %s', $url), true, $code);
    }

    /**
     * Shows error page and throws silent exception.
     *
     * @throws SilentException
     */
    public function error(int $code): never
    {
        if (headers_sent()) {
            throw new SilentException();
        }

        while (ob_get_length()) {
            ob_end_clean();
        }

        http_response_code($code);

        try {
            i(EventDispatcher::class)->dispatch(new HttpErrorEvent($code));
        } catch (Throwable $e) {
            i(CommonLogger::class)->error($e);
        }

        throw new SilentException();
    }

    /**
     * Just throws silent exception.
     *
     * @throws SilentException
     */
    public function end(): never
    {
        throw new SilentException();
    }
}
