<?php declare(strict_types=1);

namespace SWF;

use LogicException;
use function in_array;
use function strlen;

final class ResponseManager
{
    private static self $instance;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Base method for outputs.
     *
     * @throws LogicException
     */
    public function output(
        string $disposition,
        string $contents,
        string $mime,
        int $code,
        int $expire,
        ?string $filename,
        bool $exit = true,
    ): void {
        if (headers_sent()) {
            throw new LogicException('Headers already sent');
        }

        ini_set('zlib.output_compression', false);

        http_response_code($code);

        header(
            sprintf('Last-Modified: %s', gmdate('D, d M Y H:i:s \G\M\T', (int) $_SERVER['STARTED_TIME'])),
        );
        header(sprintf('Cache-Control: private, max-age=%s', $expire));
        header(sprintf('Content-Type: %s; charset=utf-8', $mime));

        if (null !== $filename) {
            header(sprintf('Content-Disposition: %s; filename="%s"', $disposition, $filename));
        } else {
            header(sprintf('Content-Disposition: %s', $disposition));
        }

        if (
            isset(
                ConfigHolder::get()->compressMimes,
                ConfigHolder::get()->compressMin,
                $_SERVER['HTTP_ACCEPT_ENCODING'],
            )
            && strlen($contents) > ConfigHolder::get()->compressMin
            && in_array($mime, ConfigHolder::get()->compressMimes, true)
            && preg_match('/(deflate|gzip)/', $_SERVER['HTTP_ACCEPT_ENCODING'], $M)
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
            $this->outputAndFlush($contents);

            fastcgi_finish_request();
        } else {
            header('Connection: close');

            $this->outputAndFlush($contents);
        }

        if ($exit) {
            exit(0);
        }
    }

    private function outputAndFlush(string $contents): void
    {
        echo $contents;

        while (ob_get_length()) {
            ob_end_flush();
        }

        flush();
    }

    /**
     * Redirect.
     *
     * @throws LogicException
     */
    public function redirect(string $uri, int $code = 302, bool $exit = true): void
    {
        if (headers_sent()) {
            throw new LogicException('Headers already sent');
        }

        header(sprintf('Location: %s', $uri), response_code: $code);

        if ($exit) {
            exit(0);
        }
    }

    /**
     * Shows error page.
     */
    public function error(int $code): never
    {
        if (!headers_sent() && !ob_get_length()) {
            http_response_code($code);

            $errorDocument = ConfigHolder::get()->errorDocument;
            if (null !== $errorDocument) {
                $errorDocument = str_replace('{CODE}', (string) $code, $errorDocument);
                if (is_file($errorDocument)) {
                    include $errorDocument;
                }
            }
        }

        exit(1);
    }
}
