<?php
declare(strict_types=1);

namespace SWF;

use Exception;
use SWF\Exception\ExitSimulationException;

final class CommandLineManager
{
    private static bool $quiet = false;

    /**
     * Gets quiet status.
     */
    public static function isQuiet(): bool
    {
        return self::$quiet;
    }

    /**
     * Sets quiet status.
     *
     * Automatically sets to true when command called with --quiet option.
     */
    public static function setQuiet(bool $quiet): void
    {
        self::$quiet = $quiet;
    }

    /**
     * Wrapped echo.
     */
    public static function write(string $string = ''): void
    {
        if (!self::$quiet) {
            echo $string;
        }
    }

    /**
     * Wrapped echo with new line.
     */
    public static function writeLn(string $string = ''): void
    {
        if (!self::$quiet) {
            echo $string, "\n";
        }
    }

    /**
     * Shows error message through regular exception and calls real exit() with code from 1 to 254.
     *
     * @throws Exception
     */
    public static function error(string $message = '', int $code = 1): never
    {
        throw ExceptionHandler::removeFileAndLine(new Exception($message, $code));
    }

    /**
     * Exits from current command through special exception.
     *
     * @throws ExitSimulationException
     */
    public static function end(): never
    {
        throw new ExitSimulationException();
    }
}
