<?php declare(strict_types=1);

namespace SWF;

use InvalidArgumentException;
use Psr\Log\LogLevel;
use ReflectionClass;
use SWF\Event\BeforeAllEvent;
use SWF\Event\BeforeCommandEvent;
use SWF\Event\BeforeControllerEvent;
use SWF\Event\ShutdownEvent;
use SWF\Exception\CoreException;
use SWF\Exception\DatabaserException;
use SWF\Interface\DatabaserInterface;
use SWF\Router\CommandRouter;
use SWF\Router\ControllerRouter;
use Throwable;

abstract class AbstractRunner extends AbstractBase
{
    private static int $initialized = 0;

    public function __construct(AbstractConfig $config)
    {
        if (self::$initialized++) {
            return;
        }

        try {
            $this->init($config);
        } catch (Throwable $e) {
            $this->error($e, 'cli' !== PHP_SAPI);
        }
    }

    /**
     * @throws Throwable
     */
    private function init(AbstractConfig $config): void
    {
        $_SERVER['STARTED_TIME'] = gettimeofday(true);

        ini_set('display_errors', 'cli' === PHP_SAPI);
        ini_set('error_reporting', E_ALL);
        ini_set('ignore_user_abort', true);

        setlocale(LC_ALL, 'C');

        mb_internal_encoding('UTF-8');

        ConfigHolder::set($config);

        set_error_handler($this->errorHandler(...));

        ini_set('date.timezone', ConfigHolder::get()->timezone);

        $_SERVER['HTTP_SCHEME'] = empty($_SERVER['HTTPS']) || 'off' === $_SERVER['HTTPS'] ? 'http' : 'https';

        $_SERVER['HTTP_HOST'] ??= 'localhost';

        $_SERVER['HTTP_URL'] = sprintf('%s://%s', $_SERVER['HTTP_SCHEME'], $_SERVER['HTTP_HOST']);

        $_SERVER['USER_URL'] = $this->getUserUrl() ?? $_SERVER['HTTP_URL'];

        $_SERVER['REMOTE_ADDR'] ??= '0.0.0.0';

        $_SERVER['REQUEST_METHOD'] ??= 'GET';

        $_SERVER['REQUEST_URI'] = $_SERVER['REDIRECT_REQUEST_URI'] ?? $_SERVER['REQUEST_URI'] ?? '/';

        $_SERVER['QUERY_STRING'] = explode('?', $_SERVER['REQUEST_URI'], 2)[1] ?? '';

        register_shutdown_function($this->cleanupAndDispatchAtShutdown(...));
    }

    public function runController(): void
    {
        try {
            $action = ControllerRouter::getInstance()->getCurrentAction();
            if (null === $action) {
                ResponseManager::getInstance()->error(404);
            }

            [$_SERVER['ROUTER_ACTION'], $_SERVER['ROUTER_ALIAS']] = $action;

            EventDispatcher::getInstance()->dispatch(new BeforeAllEvent());
            EventDispatcher::getInstance()->dispatch(new BeforeControllerEvent());

            CallbackHandler::normalize($_SERVER['ROUTER_ACTION'])();
        } catch (Throwable $e) {
            $this->error($e, true);
        }
    }

    public function runCommand(): void
    {
        try {
            $action = CommandRouter::getInstance()->getCurrentAction();
            if (null === $action) {
                throw new InvalidArgumentException('Command not found');
            }

            [$_SERVER['ROUTER_ACTION'], $_SERVER['ROUTER_ALIAS']] = $action;

            EventDispatcher::getInstance()->dispatch(new BeforeAllEvent());
            EventDispatcher::getInstance()->dispatch(new BeforeCommandEvent());

            CallbackHandler::normalize($_SERVER['ROUTER_ACTION'])();
        } catch (Throwable $e) {
            $this->error($e, false);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function getUserUrl(): ?string
    {
        if (null === ConfigHolder::get()->url) {
            return null;
        }

        $url = parse_url(ConfigHolder::get()->url);
        if (empty($url) || !isset($url['host'])) {
            throw new InvalidArgumentException('Incorrect URL in configuration');
        }

        if (isset($url['port'])) {
            return sprintf('%s://%s:%s', $url['scheme'] ?? 'http', $url['host'], $url['port']);
        }

        return sprintf('%s://%s', $url['scheme'] ?? 'http', $url['host']);
    }

    /**
     * @throws CoreException
     */
    private function errorHandler(int $code, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $code)) {
            return true;
        }

        if (in_array($code, [E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED], true)) {
            CommonLogger::getInstance()->log(LogLevel::NOTICE, $message, options: [
                'file' => $file,
                'line' => $line,
            ]);

            return true;
        }

        if (in_array($code, [E_WARNING, E_USER_WARNING, E_STRICT], true) && !ConfigHolder::get()->strict) {
            CommonLogger::getInstance()->log(LogLevel::WARNING, $message, options: [
                'file' => $file,
                'line' => $line,
            ]);

            return true;
        }

        throw (new CoreException($message))->setFileAndLine($file, $line);
    }

    /**
     * @throws Throwable
     */
    private function cleanupAndDispatchAtShutdown(): void
    {
        foreach ((array) (new ReflectionClass(AbstractBase::class))->getStaticPropertyValue('shared') as $class) {
            if ($class instanceof DatabaserInterface && $class->isInTrans()) {
                try {
                    $class->rollback();
                } catch (DatabaserException) {
                }
            }
        }

        EventDispatcher::getInstance()->dispatch(new ShutdownEvent(), true);
    }

    private function error(Throwable $e, bool $isController): never
    {
        ListenerProvider::getInstance()->removeAllListeners(true);

        CommonLogger::getInstance()->log(LogLevel::ERROR, $e);
        CommonLogger::getInstance()->log(LogLevel::EMERGENCY, 'Application terminated!', options: [
            'append_file_and_line' => false,
        ]);

        if ($isController) {
            ResponseManager::getInstance()->error(500);
        }

        exit(1);
    }
}
