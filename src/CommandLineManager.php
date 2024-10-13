<?php
declare(strict_types=1);

namespace SWF;

use Exception;
use LogicException;
use SWF\Exception\ExitSimulationException;

final class CommandLineManager
{
    private bool $quiet = false;

    public function __construct()
    {
        if ('cli' !== PHP_SAPI) {
            throw new LogicException('Please, use this class only in CLI mode');
        }
    }

    /**
     * Gets quiet status.
     */
    public function isQuiet(): bool
    {
        return $this->quiet;
    }

    /**
     * Sets quiet status.
     *
     * Automatically sets to true when command called with --quiet option.
     */
    public function setQuiet(bool $quiet): self
    {
        $this->quiet = $quiet;

        return $this;
    }

    /**
     * Wrapped echo.
     */
    public function write(string $string = ''): self
    {
        if (!$this->quiet) {
            echo $string;
        }

        return $this;
    }

    /**
     * Wrapped echo with new line.
     */
    public function writeLn(string $string = ''): self
    {
        if (!$this->quiet) {
            echo $string, "\n";
        }

        return $this;
    }

    /**
     * Shows error message through regular exception and calls real exit() with code from 1 to 254.
     *
     * @throws Exception
     */
    public function error(string $message = '', int $code = 1): never
    {
        throw ExceptionHandler::removeFileAndLine(new Exception($message, $code));
    }

    /**
     * Exits from current command through special exception.
     *
     * @throws ExitSimulationException
     */
    public function end(): never
    {
        throw new ExitSimulationException();
    }
}
