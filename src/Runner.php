<?php
declare(strict_types=1);

namespace SWF;

use Composer\Autoload\ClassLoader;
use Exception;
use InvalidArgumentException;
use SWF\Event\AfterCommandEvent;
use SWF\Event\AfterControllerEvent;
use SWF\Event\BeforeCommandEvent;
use SWF\Event\BeforeControllerEvent;
use SWF\Exception\ExitSimulationException;
use Throwable;
use function in_array;
use function is_int;
use function strlen;

final class Runner
{
    private static self $instance;

    public function __construct(ClassLoader $loader, AbstractSystemConfig $systemConfig)
    {
        if (isset(self::$instance)) {
            self::$instance->shutdown(ExceptionHandler::removeFileAndLine(new Exception('Runner can be instantiated only once')), true);
        }

        LoaderStorage::$loader = $loader;

        ConfigStorage::$system = $systemConfig;

        set_error_handler($this->errorHandler(...));

        try {
            $this->prepareEnvironment();

            i(ActionManager::class)->prepare();
        } catch (Throwable $e) {
            $this->shutdown($e, true);
        }

        self::$instance = $this;
    }

    public function runController(): void
    {
        try {
            $action = i(ControllerProvider::class)->getCurrentAction();
            if (null === $action->getMethod()) {
                ResponseManager::error(404);
            }

            $_SERVER['ACTION_TYPE'] = $action->getType()->value;

            $_SERVER['ACTION_METHOD'] = $action->getMethod();

            $_SERVER['ACTION_ALIAS'] = $action->getAlias();

            i(EventDispatcher::class)->dispatch(new BeforeControllerEvent());

            try {
                CallbackHandler::normalize($action->getMethod())();
            } catch (ExitSimulationException) {
            }

            i(EventDispatcher::class)->dispatch(new AfterControllerEvent());
        } catch (Throwable $e) {
            $this->shutdown($e);
        }
    }

    public function runCommand(): void
    {
        try {
            $action = i(CommandProvider::class)->getCurrentAction();
            if (null === $action->getMethod()) {
                CommandLineManager::error(sprintf('Command %s not found', $action->getAlias()));
            }

            $_SERVER['ACTION_TYPE'] = $action->getType()->value;

            $_SERVER['ACTION_METHOD'] = $action->getMethod();

            $_SERVER['ACTION_ALIAS'] = $action->getAlias();

            i(EventDispatcher::class)->dispatch(new BeforeCommandEvent());

            try {
                CallbackHandler::normalize($action->getMethod())();
            } catch (ExitSimulationException) {
            }

            i(EventDispatcher::class)->dispatch(new AfterCommandEvent());
        } catch (Throwable $e) {
            $this->shutdown($e);
        }
    }

    /**
     * @throws Exception
     */
    private static function prepareEnvironment(): void
    {
        try {
            ini_set('date.timezone', ConfigStorage::$system->timezone);
        } catch (Exception) {
            throw ExceptionHandler::removeFileAndLine(new Exception('Incorrect timezone in system configuration'));
        }

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

        if (in_array($code, [E_WARNING, E_USER_WARNING, E_STRICT], true) && !ConfigStorage::$system->strict) {
            i(CommonLogger::class)->warning($message, options: ['file' => $file, 'line' => $line]);

            return true;
        }

        throw ExceptionHandler::overrideFileAndLine(new Exception($message), $file, $line);
    }

    private function shutdown(Throwable $e, bool $hard = false): void
    {
        while (ob_get_length()) {
            ob_end_clean();
        }

        if (strlen($e->getMessage()) > 0) {
            i(CommonLogger::class)->critical($e);
        }

        $code = $e->getCode();

        if (PHP_SAPI === 'cli') {
            exit(is_int($code) ? min(max($code, 1), 254) : 1);
        }

        ResponseManager::errorPage(is_int($code) && $code > 0 ? min(max($code, 100), 599) : 500);

        if ($hard) {
            exit(1);
        }
    }
}
