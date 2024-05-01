<?php declare(strict_types=1);

namespace SWF;

use ReflectionClass;
use Throwable;

final class ExceptionHandler
{
    /**
     * @template T
     *
     * @param T $e
     *
     * @return T
     */
    public static function overrideFileAndLine($e, string $file, int $line)
    {
        if (!$e instanceof Throwable) {
            return $e;
        }

        try {
            $eRef = new ReflectionClass($e);

            $eRef->getProperty('file')->setValue($e, $file);
            $eRef->getProperty('line')->setValue($e, $line);
        } catch (Throwable) {
        }

        return $e;
    }
}
