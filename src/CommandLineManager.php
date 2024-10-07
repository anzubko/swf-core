<?php declare(strict_types=1);

namespace SWF;

use Exception;
use SWF\Exception\ExitSimulationException;

final class CommandLineManager
{
    private bool $quiet = false;

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
     * Finish current command or any listener through special exception.
     *
     * @throws ExitSimulationException
     */
    public function end(): never
    {
        throw new ExitSimulationException();
    }
}
