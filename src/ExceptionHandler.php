<?php declare(strict_types=1);

namespace SWF;

use ReflectionClass;
use Throwable;

final class ExceptionHandler
{
    /**
     * @template T
     *
     * @param T $exception
     *
     * @return T
     */
    public static function overrideFileAndLine($exception, string $file, int $line)
    {
        if (!$exception instanceof Throwable) {
            return $exception;
        }

        try {
            $eRef = new ReflectionClass($exception);

            $eRef->getProperty('file')->setValue($exception, $file);
            $eRef->getProperty('line')->setValue($exception, $line);
        } catch (Throwable) {
        }

        return $exception;
    }

    /**
     * @template T
     *
     * @param T $exception
     *
     * @return T
     */
    public static function removeFileAndLine($exception)
    {
        return self::overrideFileAndLine($exception, '', 0);
    }
}
