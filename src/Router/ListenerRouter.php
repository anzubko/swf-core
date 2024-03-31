<?php declare(strict_types=1);

namespace SWF\Router;

use InvalidArgumentException;
use Psr\Log\LogLevel;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use RuntimeException;
use SWF\AbstractRouter;
use SWF\Attribute\AsListener;
use SWF\CallbackHandler;
use SWF\CommonLogger;
use SWF\ConfigHolder;
use Throwable;
use function in_array;

final class ListenerRouter extends AbstractRouter
{
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
        $this->readCache(sprintf('%s/listeners.php', ConfigHolder::get()->sysCacheDir));
    }

    /**
     * Adds listener.
     *
     * @param bool $disposable Listener can be called only once.
     * @param bool $persistent Listener can only be removed with the force parameter.
     *
     * @throws InvalidArgumentException
     */
    public function add(callable $callback, bool $disposable = false, bool $persistent = false): void
    {
        $params = (new ReflectionFunction($callback(...)))->getParameters();
        $type = $params ? $params[0]->getType() : null;
        if (null === $type) {
            throw new InvalidArgumentException('Listener must have first parameter with declared type');
        }

        self::$cache['listeners'][] = [
            'callback' => $callback,
            'type' => (string) $type,
            'disposable' => $disposable,
            'persistent' => $persistent,
        ];
    }

    /**
     * Removes listeners by event type.
     *
     * @param string|string[] $type
     */
    public function removeByType(array|string $type, bool $force = false): void
    {
        $type = (array) $type;
        foreach (self::$cache['listeners'] as $i => $listener) {
            if (($force || !$listener['persistent']) && in_array($listener['type'], $type, true)) {
                unset(self::$cache['listeners'][$i]);
            }
        }
    }

    /**
     * Removes all listeners.
     */
    public function removeAll(bool $force = false): void
    {
        if ($force) {
            self::$cache['listeners'] = [];
        } else {
            foreach (self::$cache['listeners'] as $i => $listener) {
                if (!$listener['persistent']) {
                    unset(self::$cache['listeners'][$i]);
                }
            }
        }
    }

    /**
     * Gets listeners for event.
     *
     * @return iterable<callable>
     *
     * @throws Throwable
     */
    public function getForEvent(object $event): iterable
    {
        foreach (self::$cache['listeners'] as $i => &$listener) {
            if (!$event instanceof $listener['type']) {
                continue;
            }

            $listener['callback'] = CallbackHandler::normalize($listener['callback']);
            if ($listener['disposable']) {
                unset(self::$cache['listeners'][$i]);
            }

            yield $listener['callback'];
        }
    }

    /**
     * @param mixed[] $initialCache
     */
    protected function rebuildCache(array $initialCache): void
    {
        self::$cache = $initialCache;
        self::$cache['listeners'] = [];

        foreach (get_declared_classes() as $class) {
            if (!str_starts_with($class, $this->appNs)) {
                continue;
            }

            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes(AsListener::class) as $attribute) {
                    if ($method->isConstructor()) {
                        CommonLogger::getInstance()->log(LogLevel::WARNING, "Constructor can't be a listener", options: [
                            'file' => $method->getFileName(),
                            'line' => $method->getStartLine(),
                        ]);
                        continue;
                    }

                    $params = $method->getParameters();
                    $type = $params ? $params[0]->getType() : null;
                    if (null === $type) {
                        CommonLogger::getInstance()->log(LogLevel::WARNING, 'Listener must have first parameter with declared type', options: [
                            'file' => $method->getFileName(),
                            'line' => $method->getStartLine(),
                        ]);
                        continue;
                    }

                    $instance = $attribute->newInstance();
                    self::$cache['listeners'][] = [
                        'callback' => sprintf('%s::%s', $class, $method->name),
                        'type' => (string) $type,
                        'disposable' => $instance->disposable,
                        'persistent' => $instance->persistent,
                        'priority' => $instance->priority,
                    ];
                }
            }
        }

        usort(self::$cache['listeners'], fn($a, $b) => $a['priority'] <=> $b['priority']);

        foreach (array_keys(self::$cache['listeners']) as $i) {
            unset(self::$cache['listeners'][$i]['priority']);
        }
    }
}
