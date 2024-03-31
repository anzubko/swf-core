<?php declare(strict_types=1);

namespace SWF;

use DateTime;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;
use SWF\Event\LoggerEvent;
use Throwable;
use function count;
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
     *
     * @see LogLevel
     */
    public function log(mixed $level, string|Stringable $message, array $context = [], array $options = []): void
    {
        set_error_handler(fn() => true);

        if (!is_string($level)) {
            $level = LogLevel::ERROR;
        }

        if ($message instanceof Throwable) {
            $detailed = (string) $message;
        } else {
            $detailed = $message;
            if ($options['append_file_and_line'] ?? true) {
                $which = $this->getFileAndLine($options);
                if (null !== $which) {
                    $detailed = sprintf('%s in %s:%s', $detailed, $which['file'], $which['line']);

                    if (!isset($options['destination'])) {
                        try {
                            EventDispatcher::getInstance()->dispatch(
                                new LoggerEvent(
                                    $level,
                                    (string) $message,
                                    $which['file'],
                                    $which['line'],
                                    $context,
                                )
                            );
                        } catch (Throwable) {
                        }
                    }
                }
            }

            if (count($context) > 0) {
                $detailed = sprintf('%s %s', $detailed, json_encode($context, JSON_UNESCAPED_UNICODE));
            }
        }

        $detailed = sprintf('[%s] %s', ucfirst($level), $detailed);

        if (!isset($options['destination'])) {
            error_log($detailed);

            if (null !== ConfigHolder::get()->errorLog) {
                $options['destination'] = ConfigHolder::get()->errorLog;
            }
        }

        if (isset($options['destination'])) {
            FileHandler::put($options['destination'], sprintf("[%s] %s\n", $this->getTime(), $detailed), FILE_APPEND);
        }

        restore_error_handler();
    }

    /**
     * @param mixed[] $options
     *
     * @return array{file:string, line:int}|null
     */
    private function getFileAndLine(array $options): ?array
    {
        if (isset($options['file'], $options['line'])) {
            return [
                'file' => (string) $options['file'],
                'line' => (int) $options['line'],
            ];
        }

        if (is_array($options['trace']) && isset($options['trace'][0]['file'], $options['trace'][0]['line'])) {
            return [
                'file' => (string) $options['trace'][0]['file'],
                'line' => (int) $options['trace'][0]['line'],
            ];
        }

        $trace = debug_backtrace(3);
        foreach ($trace as $i => $item) {
            if (isset($item['object']) && ($item['object'] instanceof self || $item['object'] instanceof LoggerInterface)) {
                continue;
            }

            return [
                'file' => $trace[$i - 1]['file'] ?? '',
                'line' => $trace[$i - 1]['line'] ?? 0,
            ];
        }

        return null;
    }

    private function getTime(): string
    {
        $now = new DateTime();

        try {
            $now->setTimezone(new DateTimeZone(ConfigHolder::get()->timezone));
        } catch (Throwable) {
        }

        return $now->format('d-M-Y H:i:s e');
    }
}
