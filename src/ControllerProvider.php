<?php declare(strict_types=1);

namespace SWF;

use LogicException;
use RuntimeException;
use function count;
use function is_string;

final class ControllerProvider
{
    private static ActionCache $cache;

    private static self $instance;

    /**
     * @throws LogicException
     * @throws RuntimeException
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * @throws LogicException
     * @throws RuntimeException
     */
    private function __construct()
    {
        self::$cache = ActionManager::getInstance()->getCache(ControllerProcessor::class);
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

        $action = $actions[$_SERVER['REQUEST_METHOD']] ?? $actions['ANY'] ?? null;
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
     *
     * @throws LogicException
     */
    public function genUrl(string $action, string|int|float|null ...$params): string
    {
        $pCount = count($params);

        $index = self::$cache->data['actions']["$action:$pCount"] ?? null;
        if (null === $index) {
            if (0 === $pCount) {
                throw new LogicException(sprintf('Unable to make URL by action "%s"', $action));
            }

            throw new LogicException(sprintf('Unable to make URL by action "%s" and %s parameter%s', $action, $pCount, 1 === $pCount ? '' : 's'));
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

    public function showAll(): void
    {
        $controllers = [];
        foreach (self::$cache->data['static'] as $path => $actions) {
            foreach ($actions as $method => $action) {
                $action = (array) $action;
                $controllers[$path]['methods'][] = $method;
                $controllers[$path]['action'] = $action;
            }
        }

        foreach (self::$cache->data['dynamic'] as $actions) {
            foreach ($actions[0] as $method => $action) {
                $action = (array) $action;
                $i = self::$cache->data['actions'][sprintf('%s:%d', $action[0], $actions[1])];
                $path = implode(self::$cache->data['urls'][$i]);
                $controllers[$path]['methods'][] = $method;
                $controllers[$path]['action'] = $action;
            }
        }

        if (count($controllers) === 0) {
            echo "No controllers found.\n";
            exit;
        }

        echo "Available controllers:\n";

        ksort($controllers);
        foreach ($controllers as $path => $controller) {
            echo sprintf("\n%s %s --> %s\n", implode('|', $controller['methods']), $path, $controller['action'][0]);

            if (isset($controller['action'][1])) {
                echo sprintf("  alias: %s\n", $controller['action'][1]);
            }
        }

        echo "\n";
    }
}
