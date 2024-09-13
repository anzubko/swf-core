<?php declare(strict_types=1);

namespace SWF;

use SWF\Event\ResponseEvent;
use SWF\Event\HttpErrorEvent;
use Throwable;
use function is_resource;
use function is_string;

final class ResponseManager
{
    private static HeaderRegistry $headers;

    /**
     * Returns headers registry.
     */
    public static function headers(): HeaderRegistry
    {
        return self::$headers ??= new HeaderRegistry();
    }

    /**
     * Sends response.
     *
     * @param string|resource $body
     *
     * @throws Throwable
     */
    public static function send(mixed $body, int $code = 200, bool $exit = true): void
    {
        $body = i(EventDispatcher::class)->dispatch(new ResponseEvent(self::$headers, $body))->getBody();

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

        if ($exit) {
            exit(0);
        }
    }

    /**
     * Redirects to specified url.
     */
    public static function redirect(string $url, int $code = 302, bool $exit = true): void
    {
        header(sprintf('Location: %s', $url), true, $code);

        if ($exit) {
            exit(0);
        }
    }

    /**
     * Shows error page and exit.
     */
    public static function error(int $code): never
    {
        if (headers_sent()) {
            exit(1);
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

        exit(1);
    }
}
