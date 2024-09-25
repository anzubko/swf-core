<?php declare(strict_types=1);

namespace SWF;

use Exception;
use InvalidArgumentException;
use SWF\Event\AfterCommandEvent;
use SWF\Event\AfterControllerEvent;
use SWF\Event\BeforeCommandEvent;
use SWF\Event\BeforeControllerEvent;
use SWF\Event\ShutdownEvent;
use SWF\Exception\DatabaserException;
use SWF\Exception\ExitSimulationException;
use SWF\Interface\DatabaserInterface;
use Throwable;
use function in_array;
use function is_int;
use function strlen;

final class Runner
{
    private static self $instance;

    public static function getInstance(): self
    {
        if (!isset(self::$instance)) {
            self::shutdown(ExceptionHandler::removeFileAndLine(new Exception('Runner must be instantiated before')), true);
        }

        return self::$instance;
    }

    /**
     * @param class-string<AbstractSystemConfig> $systemConfigName
     */
    public function __construct(string $systemConfigName)
    {
        if (isset(self::$instance)) {
            self::shutdown(ExceptionHandler::removeFileAndLine(new Exception('Runner can be instantiated only once')), true);
        }

        ConfigStorage::$system = i($systemConfigName);

        set_error_handler(self::errorHandler(...));

        try {
            self::setTimezone();
            self::setStartupParameters();
        } catch (Throwable $e) {
            self::shutdown($e);
        }

        register_shutdown_function(self::cleanupAndDispatchAtShutdown(...));

        self::$instance = $this;
    }

    public function runController(): void
    {
        try {
            $action = i(ControllerProvider::class)->getCurrentAction();
            if (null === $action->method) {
                i(ResponseManager::class)->error(404);
            }

            $_SERVER['ACTION_TYPE'] = $action->type->value;

            $_SERVER['ACTION_METHOD'] = $action->method;

            $_SERVER['ACTION_ALIAS'] = $action->alias;

            i(EventDispatcher::class)->dispatch(new BeforeControllerEvent());

            try {
                CallbackHandler::normalize($action->method)();
            } catch (ExitSimulationException) {
            }

            i(EventDispatcher::class)->dispatch(new AfterControllerEvent());
        } catch (Throwable $e) {
            self::shutdown($e);
        }
    }

    public function runCommand(): void
    {
        try {
            $action = i(CommandProvider::class)->getCurrentAction();
            if (null === $action->method) {
                i(CommandLineManager::class)->error(sprintf('Command %s not found', $action->alias));
            }

            $_SERVER['ACTION_TYPE'] = $action->type->value;

            $_SERVER['ACTION_METHOD'] = $action->method;

            $_SERVER['ACTION_ALIAS'] = $action->alias;

            i(EventDispatcher::class)->dispatch(new BeforeCommandEvent());

            try {
                CallbackHandler::normalize($action->method)();
            } catch (ExitSimulationException) {
            }

            i(EventDispatcher::class)->dispatch(new AfterCommandEvent());
        } catch (Throwable $e) {
            self::shutdown($e);
        }
    }

    /**
     * @throws Exception
     */
    private static function setTimezone(): void
    {
        try {
            ini_set('date.timezone', ConfigStorage::$system->timezone);
        } catch (Exception) {
            throw ExceptionHandler::removeFileAndLine(new Exception('Incorrect timezone in system configuration'));
        }
    }

    /**
     * @throws Exception
     */
    private static function setStartupParameters(): void
    {
        try {
            $userUrl = new UrlNormalizer(ConfigStorage::$system->url);
        } catch (InvalidArgumentException) {
            throw ExceptionHandler::removeFileAndLine(new Exception('Incorrect URL in system configuration'));
        }

        $_SERVER['USER_SCHEME'] = $userUrl->getScheme();

        $_SERVER['USER_HOST'] = $userUrl->getHost();

        $_SERVER['USER_URL'] = $userUrl->getUrl();

        $_SERVER['HTTP_SCHEME'] = empty($_SERVER['HTTPS']) || 'off' === $_SERVER['HTTPS'] ? 'http' : 'https';

        $_SERVER['HTTP_HOST'] ??= 'localhost';

        $_SERVER['HTTP_URL'] = sprintf('%s://%s', $_SERVER['HTTP_SCHEME'], $_SERVER['HTTP_HOST']);

        $_SERVER['REQUEST_URI'] = $_SERVER['REDIRECT_REQUEST_URI'] ?? $_SERVER['REQUEST_URI'] ?? '/';

        $_SERVER['QUERY_STRING'] = explode('?', $_SERVER['REQUEST_URI'], 2)[1] ?? '';
    }

    /**
     * @throws Exception
     */
    private static function errorHandler(int $code, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $code)) {
            return true;
        }

        if (in_array($code, [E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED], true)) {
            i(CommonLogger::class)->notice($message, options: ['file' => $file, 'line' => $line]);

            return true;
        }

        throw ExceptionHandler::overrideFileAndLine(new Exception($message), $file, $line);
    }

    private static function cleanupAndDispatchAtShutdown(): void
    {
        foreach (InstanceStorage::$instances as $instance) {
            if ($instance instanceof DatabaserInterface && $instance->isInTrans()) {
                try {
                    $instance->rollback(true);
                } catch (DatabaserException) {
                }
            }
        }

        try {
            i(EventDispatcher::class)->dispatch(new ShutdownEvent());
        } catch (Throwable $e) {
            i(CommonLogger::class)->error($e);
        }
    }

    private static function shutdown(Throwable $e, bool $hard = false): void
    {
        while (ob_get_length()) {
            ob_end_clean();
        }

        if (strlen($e->getMessage()) > 0) {
            i(CommonLogger::class)->critical($e);
        }

        $code = $e->getCode();

        if (PHP_SAPI !== 'cli') {
            i(ResponseManager::class)->errorPage(is_int($code) && $code > 0 ? min(max($code, 100), 599) : 500);
        }

        i(ListenerProvider::class)->removeAllListeners(true);

        if (PHP_SAPI === 'cli') {
            exit(is_int($code) ? min(max($code, 1), 254) : 1);
        } elseif ($hard) {
            exit(1);
        }
    }
}
