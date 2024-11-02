<?php
declare(strict_types=1);

namespace SWF;

use Exception;
use SWF\Event\HttpErrorEvent;
use SWF\Event\ResponseEvent;
use SWF\Exception\ExitSimulationException;
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
    public static function send(mixed $body, int $code = 200): void
    {
        $body = i(EventDispatcher::class)->dispatch(new ResponseEvent(self::headers(), $body))->body;

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
    public static function redirect(string $url, int $code = 302): void
    {
        header(sprintf('Location: %s', $url), true, $code);
    }

    /**
     * Shows error page.
     */
    public static function errorPage(int $code): void
    {
        if (headers_sent()) {
            return;
        }

        http_response_code($code);

        try {
            i(EventDispatcher::class)->dispatch(new HttpErrorEvent($code));
        } catch (Throwable $e) {
            i(CommonLogger::class)->error($e);
        }
    }

    /**
     * Shows error page through regular exception.
     *
     * @throws Exception
     */
    public static function error(int $code = 500, string $message = ''): never
    {
        throw new Exception($message, $code);
    }

    /**
     * Exits from current controller through special exception.
     *
     * @throws ExitSimulationException
     */
    public static function end(): never
    {
        throw new ExitSimulationException();
    }
}
