<?php declare(strict_types=1);

namespace SWF\Router;

use Psr\Log\LogLevel;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use SWF\AbstractRouter;
use SWF\Attribute\AsController;
use SWF\CommonLogger;
use SWF\ConfigHolder;
use function count;
use function is_string;

final class ControllerRouter extends AbstractRouter
{
    private const CACHE_FILE = APP_DIR . '/var/cache/swf/controllers.php';

    protected static array $cache;

    private static self $instance;

    /**
     * @throws RuntimeException
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * @throws RuntimeException
     */
    private function __construct()
    {
        $this->readCache(self::CACHE_FILE);
    }

    /**
     * Gets current action.
     *
     * @return array{string, string|null}|null
     */
    public function getCurrentAction(): ?array
    {
        $path = explode('?', $_SERVER['REQUEST_URI'], 2)[0];

        $actions = self::$cache['static'][$path] ?? null;
        if (null === $actions && preg_match(self::$cache['regex'], $path, $M)) {
            [$actions, $keys] = self::$cache['dynamic'][$M['MARK']];

            foreach ($keys as $i => $key) {
                $_GET[$key] = $_REQUEST[$key] = $M[$i + 1];
            }
        }

        if (null === $actions) {
            return null;
        }

        $action = $actions[$_SERVER['REQUEST_METHOD']] ?? $actions[''] ?? null;
        if (null === $action) {
            return null;
        }

        if (is_string($action)) {
            $action = [$action, null];
        }

        return $action;
    }

    /**
     * Generates URL by action and optional parameters.
     */
    public function genUrl(string $action, string|int|float|null ...$params): string
    {
        $pCount = count($params);

        $index = self::$cache['actions']["$action:$pCount"] ?? null;
        if (null === $index) {
            $message = sprintf('Unable to make URL by action %s', $action);
            if ($pCount > 0) {
                $message = sprintf('%s and %s parameter%s', $message, $pCount, 1 === $pCount ? '' : 's');
            }

            CommonLogger::getInstance()->log(LogLevel::WARNING, $message, options: debug_backtrace(2)[1]);

            return '/';
        }

        $url = self::$cache['urls'][$index];
        if (0 === $pCount) {
            return $url;
        }

        foreach ($params as $i => $value) {
            if (null !== $value) {
                $url[(int) $i * 2 + 1] = $value;
            }
        }

        return implode($url);
    }

    protected function rebuildCache(array $initialCache): void
    {
        self::$cache = $initialCache;
        self::$cache['static'] = [];
        self::$cache['dynamic'] = [];
        self::$cache['urls'] = [];
        self::$cache['actions'] = [];

        $regex = [];
        foreach (get_declared_classes() as $class) {
            if (!str_starts_with($class, $this->appNs)) {
                continue;
            }

            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes(AsController::class) as $attribute) {
                    if ($method->isConstructor()) {
                        CommonLogger::getInstance()->log(LogLevel::WARNING, "Constructor can't be a controller", options: [
                            'file' => $method->getFileName(),
                            'line' => $method->getStartLine(),
                        ]);
                        continue;
                    }

                    $instance = $attribute->newInstance();
                    foreach ($instance->url as $url) {
                        foreach ($instance->method as $m) {
                            if (null !== $instance->alias) {
                                self::$cache['static'][$url][$m] = [
                                    sprintf('%s::%s', $class, $method->name),
                                    $instance->alias,
                                ];
                            } else {
                                self::$cache['static'][$url][$m] = sprintf('%s::%s', $class, $method->name);
                            }
                        }
                    }
                }
            }
        }

        foreach (self::$cache['static'] as $url => $actions) {
            if (preg_match_all('/{([^}]+)}/', $url, $M)) {
                unset(self::$cache['static'][$url]);

                $regex[] = sprintf(
                    '%s(*:%d)',
                    preg_replace('/\\\\{[^}]+}/', '([^/]+)', preg_quote($url)),
                    count(self::$cache['dynamic']),
                );

                self::$cache['dynamic'][] = [$actions, $M[1]];

                self::$cache['urls'][] = preg_split('/({[^}]+})/', $url, flags: PREG_SPLIT_DELIM_CAPTURE);

                $pCount = count($M[1]);
            } else {
                self::$cache['urls'][] = $url;

                $pCount = 0;
            }

            foreach ($actions as $action) {
                if (is_string($action)) {
                    $action = [$action, null];
                }

                foreach ($action as $name) {
                    if (null !== $name) {
                        self::$cache['actions'][sprintf('%s:%s', $name, $pCount)] = count(self::$cache['urls']) - 1;
                    }
                }
            }
        }

        self::$cache['regex'] = sprintf('{^(?|%s)$}', implode('|', $regex));
    }
}
