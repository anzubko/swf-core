<?php declare(strict_types=1);

namespace SWF;

use SWF\Event\ResponseErrorEvent;
use Throwable;
use function in_array;
use function strlen;

final class ResponseManager
{
    /**
     * Base method for outputs.
     *
     * @param string[] $compressMimes
     */
    public static function output(
        string $disposition,
        string $contents,
        string $mime,
        int $code,
        int $expire,
        ?string $filename,
        array $compressMimes = [],
        int $compressMin = 32 * 1024,
        bool $exit = true,
    ): void {
        ini_set('zlib.output_compression', false);

        http_response_code($code);

        header(sprintf('Last-Modified: %s', gmdate('D, d M Y H:i:s \G\M\T', (int) APP_STARTED)));
        header(sprintf('Cache-Control: private, max-age=%s', $expire));
        header(sprintf('Content-Type: %s; charset=utf-8', $mime));

        if (null !== $filename) {
            header(sprintf('Content-Disposition: %s; filename="%s"', $disposition, $filename));
        } else {
            header(sprintf('Content-Disposition: %s', $disposition));
        }

        if (
            strlen($contents) > $compressMin
            && in_array($mime, $compressMimes, true)
            && preg_match('/(deflate|gzip)/', $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', $M)
        ) {
            if ('gzip' === $M[1]) {
                $contents = (string) gzencode($contents, 1);
            } else {
                $contents = (string) gzdeflate($contents, 1);
            }

            header(sprintf('Content-Encoding: %s', $M[1]));
            header('Vary: Accept-Encoding');
        } else {
            header('Content-Encoding: none');
        }

        header(sprintf('Content-Length: %s', strlen($contents)));

        if (function_exists('fastcgi_finish_request')) {
            self::outputAndFlush($contents);

            fastcgi_finish_request();
        } else {
            header('Connection: close');

            self::outputAndFlush($contents);
        }

        if ($exit) {
            exit(0);
        }
    }

    /**
     * Redirects to specified url.
     */
    public static function redirect(string $uri, int $code = 302, bool $exit = true): void
    {
        header(sprintf('Location: %s', $uri), response_code: $code);

        if ($exit) {
            exit(0);
        }
    }

    /**
     * Shows error page.
     */
    public static function error(int $code): never
    {
        if (!headers_sent() && !ob_get_length()) {
            http_response_code($code);

            try {
                EventDispatcher::getInstance()->dispatch(new ResponseErrorEvent($code));
            } catch (Throwable $e) {
                CommonLogger::getInstance()->error($e);
            }
        }

        exit(1);
    }

    private static function outputAndFlush(string $contents): void
    {
        echo $contents;

        while (ob_get_length()) {
            ob_end_flush();
        }

        flush();
    }
}
