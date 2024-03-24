<?php declare(strict_types=1);

namespace SWF\Exception;

use Exception;

class CoreException extends Exception
{
    /**
     * Sets file and line in which the exception occurred.
     */
    public function setFileAndLine(string $file, int $line): self
    {
        $this->file = $file;
        $this->line = $line;

        return $this;
    }
}
