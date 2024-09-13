<?php declare(strict_types=1);

namespace SWF;

use App\Config\SystemConfig;
use Exception;
use InvalidArgumentException;
use ReflectionFunction;
use SWF\Enum\ActionTypeEnum;
use SWF\Event\BeforeAllEvent;
use SWF\Event\BeforeCommandEvent;
use SWF\Event\BeforeControllerEvent;
use SWF\Event\ShutdownEvent;
use SWF\Exception\DatabaserException;
use SWF\Interface\DatabaserInterface;
use Throwable;
use function in_array;

final class Runner
{
    private static self $instance;

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        set_error_handler($this->errorHandler(...));

        try {
            $this->setTimezone();
            $this->setStartupParameters();
        } catch (Throwable $e) {
            $this->terminate($e);
        }

        self::$instance = $this;
    }

    public function runController(): void
    {
        try {
            $action = i(ControllerProvider::class)->getCurrentAction();
            if (null === $action) {
                ResponseManager::error(404);
            }

            $_SERVER['ACTION_TYPE'] = ActionTypeEnum::CONTROLLER->value;
            $_SERVER['ACTION_METHOD'] = $action[0];
            $_SERVER['ACTION_ALIAS'] = $action[1];

            register_shutdown_function($this->cleanupAndDispatchAtShutdown(...));

            i(EventDispatcher::class)->dispatch(new BeforeAllEvent());
            i(EventDispatcher::class)->dispatch(new BeforeControllerEvent());

            CallbackHandler::normalize($_SERVER['ACTION_METHOD'])();
        } catch (Throwable $e) {
            $this->terminate($e);
        }
    }

    public function runCommand(): void
    {
        try {
            $action = i(CommandProvider::class)->getCurrentAction();
            if (null === $action) {
                throw new InvalidArgumentException('Command not found');
            }

            $_SERVER['ACTION_TYPE'] = ActionTypeEnum::COMMAND->value;
            $_SERVER['ACTION_METHOD'] = $action[0];
            $_SERVER['ACTION_ALIAS'] = $action[1];

            register_shutdown_function($this->cleanupAndDispatchAtShutdown(...));

            i(EventDispatcher::class)->dispatch(new BeforeAllEvent());
            i(EventDispatcher::class)->dispatch(new BeforeCommandEvent());

            CallbackHandler::normalize($_SERVER['ACTION_METHOD'])();
        } catch (Throwable $e) {
            $this->terminate($e);
        }
    }

    /**
     * @throws Exception
     */
    private function setTimezone(): void
    {
        ini_set('date.timezone', i(SystemConfig::class)->timezone);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function setStartupParameters(): void
    {
        $_SERVER['HTTP_SCHEME'] = empty($_SERVER['HTTPS']) || 'off' === $_SERVER['HTTPS'] ? 'http' : 'https';

        $_SERVER['HTTP_HOST'] ??= 'localhost';

        $_SERVER['HTTP_URL'] = sprintf('%s://%s', $_SERVER['HTTP_SCHEME'], $_SERVER['HTTP_HOST']);

        $_SERVER['USER_URL'] = $this->getUserUrl() ?? $_SERVER['HTTP_URL'];

        $_SERVER['REQUEST_URI'] = $_SERVER['REDIRECT_REQUEST_URI'] ?? $_SERVER['REQUEST_URI'] ?? '/';

        $_SERVER['QUERY_STRING'] = explode('?', $_SERVER['REQUEST_URI'], 2)[1] ?? '';
    }

    /**
     * @throws InvalidArgumentException
     */
    private function getUserUrl(): ?string
    {
        if (null === i(SystemConfig::class)->url) {
            return null;
        }

        $url = parse_url(i(SystemConfig::class)->url);
        if (empty($url) || !isset($url['host'])) {
            throw new InvalidArgumentException('Incorrect URL in configuration');
        }

        $url['scheme'] ??= 'http';
        if (isset($url['port'])) {
            return sprintf('%s://%s:%s', $url['scheme'], $url['host'], $url['port']);
        }

        return sprintf('%s://%s', $url['scheme'], $url['host']);
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
        foreach ((new ReflectionFunction('i'))->getStaticVariables()['instances'] as $class) {
            if ($class instanceof DatabaserInterface && $class->isInTrans()) {
                try {
                    $class->rollback(true);
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

    private function terminate(Throwable $e): never
    {
        i(CommonLogger::class)->error($e);

        i(ListenerProvider::class)->removeAllListeners(true);

        if ('cli' === PHP_SAPI) {
            exit(1);
        }

        ResponseManager::error(500);
    }
}
