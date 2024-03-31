<?php declare(strict_types=1);

namespace SWF;

use DateTime;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;
use SWF\Event\ExceptionEvent;
use Throwable;
use function is_array;
use function is_string;

final class CommonLogger
{
    private static self $instance;

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed[] $context
     * @param mixed[] $options
     */
    public function log(mixed $level, string|Stringable $message, array $context = [], array $options = []): void
    {
        set_error_handler(fn() => true);

        if (!is_string($level)) {
            $level = LogLevel::ERROR;
        }

        if ($message instanceof Throwable) {
            try {
                EventDispatcher::getInstance()->dispatch(new ExceptionEvent($message));
            } catch (Throwable) {
            }

            $complexMessage = (string) $message;
        } else {
            $complexMessage = $message;

            if ($options['append_file_and_line'] ?? true) {
                [$file, $line] = $this->getFileAndLine($options);

                if (isset($file, $line)) {
                    $complexMessage = sprintf('%s in %s:%s', $complexMessage, $file, $line);
                }
            }

            if (!empty($context)) {
                $complexMessage = sprintf('%s %s', $complexMessage, json_encode($context, JSON_UNESCAPED_UNICODE));
            }
        }

        $complexMessage = sprintf('[%s] %s', ucfirst($level), $complexMessage);

        if (!isset($options['destination'])) {
            error_log($complexMessage);

            if (null !== ConfigHolder::get()->errorLog) {
                $options['destination'] = ConfigHolder::get()->errorLog;
            }
        }

        if (isset($options['destination'])) {
            $when = new DateTime();
            try {
                $when->setTimezone(new DateTimeZone(ConfigHolder::get()->timezone));
            } catch (Throwable) {
            }

            $complexMessage = sprintf("[%s] %s\n", $when->format('d-M-Y H:i:s e'), $complexMessage);

            FileHandler::put($options['destination'], $complexMessage, FILE_APPEND);
        }

        restore_error_handler();
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

        if (is_array($options['trace']) && isset($options['trace'][0]['file'], $options['trace'][0]['line'])) {
            return [
                (string) $options['trace'][0]['file'],
                (int) $options['trace'][0]['line'],
            ];
        }

        $trace = debug_backtrace(3);
        foreach ($trace as $i => $item) {
            if (isset($item['object']) && ($item['object'] instanceof self || $item['object'] instanceof LoggerInterface)) {
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
