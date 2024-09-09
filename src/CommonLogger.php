<?php declare(strict_types=1);

namespace SWF;

use App\Config\SystemConfig;
use DateTime;
use DateTimeZone;
use Exception;
use JsonException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;
use SWF\Event\LogEvent;
use Throwable;
use function is_string;

final class CommonLogger implements LoggerInterface
{
    private static self $instance;

    private ?DateTimeZone $timezone;

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

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

        if (null !== i(SystemConfig::class)->customLog) {
            $this->customLog(strtr(i(SystemConfig::class)->customLog, ['{ENV}' => i(SystemConfig::class)->env]), $complexMessage);
        }

        try {
            EventDispatcher::getInstance()->dispatch(
                new LogEvent(
                    level: $level,
                    complexMessage: $complexMessage,
                    exception: $message instanceof Throwable ? $message : null,
                ),
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
                $this->timezone = new DateTimeZone(i(SystemConfig::class)->timezone);
            } catch (Exception) {
                $this->timezone = new DateTimeZone('UTC');
            }
        }

        FileHandler::put($file, sprintf('[%s] %s', (new DateTime())->setTimezone($this->timezone)->format('d-M-Y H:i:s.v e'), $message), FILE_APPEND);
    }

    /**
     * @param mixed[] $context
     * @param mixed[] $options
     */
    private function getComplexMessage(string $level, string|Stringable $message, array $context, array $options): string
    {
        $complexMessage = (string) $message;
        if (!$message instanceof Throwable) {
            [$file, $line] = $this->getFileAndLine($options);

            if (isset($file, $line)) {
                $complexMessage = sprintf('%s in %s:%s', $complexMessage, $file, $line);
            }
        }

        if (!empty($context)) {
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
        if (isset($options['file'], $options['line'])) {
            return [
                (string) $options['file'],
                (int) $options['line'],
            ];
        }

        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($trace as $i => $item) {
            if (isset($item['object']) && $item['object'] instanceof LoggerInterface) {
                continue;
            }

            return [
                $trace[$i - 1]['file'] ?? '',
                $trace[$i - 1]['line'] ?? 0,
            ];
        }

        return [null, null];
    }
}
