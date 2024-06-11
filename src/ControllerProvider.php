<?php declare(strict_types=1);

namespace SWF;

use function count;
use function is_string;

final class ControllerProvider
{
    private static ActionCache $cache;

    private static self $instance;

    private function __construct()
    {
        self::$cache = ActionManager::getInstance()->getCache(ControllerProcessor::class);
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Gets current action.
     *
     * @return array{string, string|null}|null
     */
    public function getCurrentAction(): ?array
    {
        $path = explode('?', $_SERVER['REQUEST_URI'], 2)[0];

        $actions = self::$cache->data['static'][$path] ?? null;
        if (null === $actions && preg_match(self::$cache->data['regex'], $path, $M)) {
            [$actions, $keys] = self::$cache->data['dynamic'][$M['MARK']];

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

        $index = self::$cache->data['actions']["$action:$pCount"] ?? null;
        if (null === $index) {
            $message = sprintf('Unable to make URL by action %s', $action);
            if ($pCount > 0) {
                $message = sprintf('%s and %s parameter%s', $message, $pCount, 1 === $pCount ? '' : 's');
            }

            CommonLogger::getInstance()->warning($message, options: debug_backtrace(2)[1]);

            return '/';
        }

        $url = self::$cache->data['urls'][$index];
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
}
