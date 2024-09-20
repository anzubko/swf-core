<?php declare(strict_types=1);

namespace SWF;

use DateTime;
use DateTimeZone;
use Exception;
use JsonException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;
use SWF\Event\LogEvent;
use Throwable;
use function array_key_exists;
use function count;
use function is_string;
use function strlen;

final class CommonLogger implements LoggerInterface
{
    private DateTimeZone $timezone;

    /**
     * @inheritDoc
     *
     * @param mixed[] $context
     * @param mixed[] $options
     */
    public function emergency(string|Stringable $message, array $context = [], array $options = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context, $options);
    }

    /**
     * @inheritDoc
     *
     * @param mixed[] $context
     * @param mixed[] $options
     */
    public function alert(string|Stringable $message, array $context = [], array $options = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context, $options);
    }

    /**
     * @inheritDoc
     *
     * @param mixed[] $context
     * @param mixed[] $options
     */
    public function critical(string|Stringable $message, array $context = [], array $options = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context, $options);
    }

    /**
     * @inheritDoc
     *
     * @param mixed[] $context
     * @param mixed[] $options
     */
    public function error(string|Stringable $message, array $context = [], array $options = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context, $options);
    }

    /**
     * @inheritDoc
     *
     * @param mixed[] $context
     * @param mixed[] $options
     */
    public function warning(string|Stringable $message, array $context = [], array $options = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context, $options);
    }

    /**
     * @inheritDoc
     *
     * @param mixed[] $context
     * @param mixed[] $options
     */
    public function notice(string|Stringable $message, array $context = [], array $options = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context, $options);
    }

    /**
     * @inheritDoc
     *
     * @param mixed[] $context
     * @param mixed[] $options
     */
    public function info(string|Stringable $message, array $context = [], array $options = []): void
    {
        $this->log(LogLevel::INFO, $message, $context, $options);
    }

    /**
     * @inheritDoc
     *
     * @param mixed[] $context
     * @param mixed[] $options
     */
    public function debug(string|Stringable $message, array $context = [], array $options = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context, $options);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed[] $context
     * @param mixed[] $options
     *
     * @see LogLevel
     */
    public function log(mixed $level, string|Stringable $message, array $context = [], array $options = []): void
    {
        set_error_handler(fn() => true);

        if (!is_string($level)) {
            $level = LogLevel::ERROR;
        }

        $complexMessage = $this->getComplexMessage($level, $message, $context, $options);

        error_log($complexMessage);

        if (null !== ConfigStorage::$system->customLog) {
            $this->customLog(strtr(ConfigStorage::$system->customLog, ['{ENV}' => ConfigStorage::$system->env]), $complexMessage);
        }

        try {
            i(EventDispatcher::class)->dispatch(
                new LogEvent($level, $complexMessage, $message instanceof Throwable ? $message : null),
            );
        } catch (Throwable) {
        }

        restore_error_handler();
    }

    /**
     * Just saves message in some log file with datetime in default timezone.
     */
    public function customLog(string $file, string $message): void
    {
        if (!isset($this->timezone)) {
            try {
                $this->timezone = new DateTimeZone(ConfigStorage::$system->timezone);
            } catch (Exception) {
                $this->timezone = new DateTimeZone('UTC');
            }
        }

        FileHandler::put($file, sprintf("[%s] %s\n", (new DateTime())->setTimezone($this->timezone)->format('d-M-Y H:i:s.v e'), $message), FILE_APPEND);
    }

    /**
     * @param mixed[] $context
     * @param mixed[] $options
     */
    private function getComplexMessage(string $level, string|Stringable $message, array $context, array $options): string
    {
        if ($message instanceof Throwable) {
            if (strlen($message->getFile()) > 0) {
                $complexMessage = (string) $message;
            } else {
                $complexMessage = $message->getMessage();
            }
        } else {
            $complexMessage = (string) $message;

            [$file, $line] = $this->getFileAndLine($options);

            if (isset($file, $line)) {
                $complexMessage = sprintf('%s in %s:%s', $complexMessage, $file, $line);
            }
        }

        if (count($context) > 0) {
            try {
                $encodedContext = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                $encodedContext = sprintf('[...%s...]', $e->getMessage());
            }

            $complexMessage = sprintf('%s %s', $complexMessage, $encodedContext);
        }

        return sprintf('[%s] %s', ucfirst($level), $complexMessage);
    }

    /**
     * @param mixed[] $options
     *
     * @return array{string|null, int|null}
     */
    private function getFileAndLine(array $options): array
    {
        if (array_key_exists('file', $options)) {
            if (strlen((string) $options['file']) > 0) {
                return [
                    (string) $options['file'],
                    (int) ($options['line'] ?? 0),
                ];
            } else {
                return [null, null];
            }
        }

        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS);

        foreach ($trace as $i => $item) {
            if (isset($item['object']) && $item['object'] instanceof LoggerInterface) {
                continue;
            }

            if (strlen($trace[$i - 1]['file'] ?? '') > 0) {
                return [
                    $trace[$i - 1]['file'],
                    $trace[$i - 1]['line'] ?? 0,
                ];
            } else {
                return [null, null];
            }
        }

        return [null, null];
    }
}
