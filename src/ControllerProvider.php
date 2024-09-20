<?php declare(strict_types=1);

namespace SWF;

use LogicException;
use RuntimeException;
use SWF\Enum\ActionTypeEnum;
use SWF\Exception\ExitSimulationException;
use function count;
use function is_string;

final class ControllerProvider
{
    private ?ActionCache $cache;

    /**
     * @throws LogicException
     * @throws RuntimeException
     */
    public function __construct()
    {
        $this->cache = i(ActionManager::class)->getCache(ControllerProcessor::class);
    }

    /**
     * Gets current action.
     */
    public function getCurrentAction(): CurrentActionInfo
    {
        if (null === $this->cache) {
            return new CurrentActionInfo(ActionTypeEnum::CONTROLLER);
        }

        $path = explode('?', $_SERVER['REQUEST_URI'], 2)[0];

        $actions = $this->cache->data['static'][$path] ?? null;
        if (null === $actions && preg_match($this->cache->data['regex'], $path, $M)) {
            [$actions, $keys] = $this->cache->data['dynamic'][$M['MARK']];

            foreach ($keys as $i => $key) {
                $_GET[$key] = $_REQUEST[$key] = $M[$i + 1];
            }
        }

        if (null === $actions) {
            return new CurrentActionInfo(ActionTypeEnum::CONTROLLER);
        }

        $action = $actions[$_SERVER['REQUEST_METHOD']] ?? $actions['ANY'] ?? null;
        if (null === $action) {
            return new CurrentActionInfo(ActionTypeEnum::CONTROLLER);
        }

        if (is_string($action)) {
            $action = [$action, null];
        }

        return new CurrentActionInfo(ActionTypeEnum::CONTROLLER, ...$action);
    }

    /**
     * Generates URL by action and optional parameters.
     *
     * @throws LogicException
     */
    public function genUrl(string $action, string|int|float|null ...$params): string
    {
        if (null === $this->cache) {
            return '/';
        }

        $index = $this->cache->data['actions'][sprintf('%s:%s', $action, count($params))] ?? null;
        if (null === $index) {
            if (count($params) === 0) {
                throw new LogicException(sprintf('Unable to make URL by action "%s"', $action));
            }

            throw new LogicException(sprintf('Unable to make URL by action "%s" and %s parameter%s', $action, count($params), count($params) > 1 ? 's' : ''));
        }

        $url = $this->cache->data['urls'][$index];
        if (count($params) === 0) {
            return $url;
        }

        foreach ($params as $i => $value) {
            if (null !== $value) {
                $url[(int) $i * 2 + 1] = $value;
            }
        }

        return implode($url);
    }

    /**
     * @throws ExitSimulationException
     *
     * @internal
     */
    public function listAll(): void
    {
        if (null === $this->cache) {
            return;
        }

        $controllers = [];
        foreach ($this->cache->data['static'] as $path => $actions) {
            foreach ($actions as $method => $action) {
                $action = (array) $action;
                $controllers[$path]['methods'][] = $method;
                $controllers[$path]['action'] = $action;
            }
        }

        foreach ($this->cache->data['dynamic'] as $actions) {
            foreach ($actions[0] as $method => $action) {
                $action = (array) $action;
                $i = $this->cache->data['actions'][sprintf('%s:%d', $action[0], $actions[1])];
                $path = implode($this->cache->data['urls'][$i]);
                $controllers[$path]['methods'][] = $method;
                $controllers[$path]['action'] = $action;
            }
        }

        if (count($controllers) === 0) {
            i(CommandLineManager::class)->writeLn('No controllers found')->exit();
        }

        i(CommandLineManager::class)->writeLn('Available controllers:');

        ksort($controllers);
        foreach ($controllers as $path => $controller) {
            i(CommandLineManager::class)->write(sprintf("\n%s %s --> %s\n", implode('|', $controller['methods']), $path, $controller['action'][0]));

            if (isset($controller['action'][1])) {
                i(CommandLineManager::class)->writeLn(sprintf('  alias: %s', $controller['action'][1]));
            }
        }

        i(CommandLineManager::class)->writeLn();
    }
}
