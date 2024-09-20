<?php declare(strict_types=1);

namespace SWF;

use Exception;
use InvalidArgumentException;
use SWF\Event\BeforeAllEvent;
use SWF\Event\BeforeCommandEvent;
use SWF\Event\BeforeControllerEvent;
use SWF\Event\ShutdownEvent;
use SWF\Exception\DatabaserException;
use SWF\Exception\ExitSimulationException;
use SWF\Interface\DatabaserInterface;
use Throwable;
use function in_array;
use function strlen;

abstract class AbstractRunner
{
    /**
     * @var class-string<AbstractSystemConfig>
     */
    protected string $systemConfig;

    private static self $instance;

    final public static function getInstance(): self
    {
        return self::$instance ??= new static();
    }

    final private function __construct()
    {
        ConfigStorage::$system = i($this->systemConfig);

        set_error_handler($this->errorHandler(...));

        try {
            $this->setTimezone();
            $this->setStartupParameters();
        } catch (Throwable $e) {
            $this->shutdown($e);
        }
    }

    final public function runController(): void
    {
        try {
            $action = i(ControllerProvider::class)->getCurrentAction();
            if (null === $action->method) {
                i(ResponseManager::class)->error(404);
            }

            $_SERVER['ACTION_TYPE'] = $action->type->value;

            $_SERVER['ACTION_METHOD'] = $action->method;

            $_SERVER['ACTION_ALIAS'] = $action->alias;

            register_shutdown_function($this->cleanupAndDispatchAtShutdown(...));

            i(EventDispatcher::class)->dispatch(new BeforeAllEvent());
            i(EventDispatcher::class)->dispatch(new BeforeControllerEvent());

            CallbackHandler::normalize($action->method)();
        } catch (Throwable $e) {
            $this->shutdown($e);
        }
    }

    final public function runCommand(): void
    {
        try {
            $action = i(CommandProvider::class)->getCurrentAction();
            if (null === $action->method) {
                throw ExceptionHandler::removeFileAndLine(new InvalidArgumentException(sprintf('Command %s not found', $action->alias)));
            }

            $_SERVER['ACTION_TYPE'] = $action->type->value;

            $_SERVER['ACTION_METHOD'] = $action->method;

            $_SERVER['ACTION_ALIAS'] = $action->alias;

            register_shutdown_function($this->cleanupAndDispatchAtShutdown(...));

            i(EventDispatcher::class)->dispatch(new BeforeAllEvent());
            i(EventDispatcher::class)->dispatch(new BeforeCommandEvent());

            CallbackHandler::normalize($action->method)();
        } catch (Throwable $e) {
            $this->shutdown($e);
        }
    }

    /**
     * @throws Exception
     */
    private function setTimezone(): void
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
    private function setStartupParameters(): void
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
    private function errorHandler(int $code, string $message, string $file, int $line): bool
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

    private function cleanupAndDispatchAtShutdown(): void
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

    private function shutdown(Throwable $e): void
    {
        if ($e instanceof ExitSimulationException) {
            return;
        }

        while (ob_get_length()) {
            ob_end_clean();
        }

        if (strlen($e->getMessage()) > 0) {
            i(CommonLogger::class)->error($e);
        }

        if (PHP_SAPI !== 'cli') {
            i(ResponseManager::class)->errorPage($e->getCode() > 0 ? $e->getCode() : 500);
        }

        i(ListenerProvider::class)->removeAllListeners(true);

        if (PHP_SAPI === 'cli') {
            exit(1);
        }
    }
}
