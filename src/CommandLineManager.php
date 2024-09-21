<?php declare(strict_types=1);

namespace SWF;

use Exception;
use SWF\Exception\ExitSimulationException;

final class CommandLineManager
{
    /**
     * Wrapped echo.
     */
    public function write(string $string = ''): self
    {
        echo $string;

        return $this;
    }

    /**
     * Wrapped echo with new line.
     */
    public function writeLn(string $string = ''): self
    {
        echo $string, "\n";

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
     * Exit(0) call simulation through special exception.
     *
     * @throws ExitSimulationException
     */
    public function exit(): never
    {
        throw new ExitSimulationException();
    }
}
