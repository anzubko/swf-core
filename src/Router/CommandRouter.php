<?php declare(strict_types=1);

namespace SWF\Router;

use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use SWF\AbstractRouter;
use SWF\Attribute\AsCommand;
use SWF\CommonLogger;
use SWF\InstanceHolder;

final class CommandRouter extends AbstractRouter
{
    private const CACHE_FILE = APP_DIR . '/var/cache/swf/commands.php';

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
        if (!isset($_SERVER['argv'][1])) {
            return null;
        }

        $action = self::$cache['commands'][$_SERVER['argv'][1]] ?? null;
        if (null === $action) {
            return null;
        }

        return [$action, null];
    }

    protected function rebuildCache(array $initialCache): void
    {
        self::$cache = $initialCache;
        self::$cache['commands'] = [];

        foreach (get_declared_classes() as $class) {
            if (!str_starts_with($class, self::APP_NS)) {
                continue;
            }

            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes(AsCommand::class) as $attribute) {
                    if ($method->isConstructor()) {
                        InstanceHolder::get(CommonLogger::class)->warning("Constructor can't be a command", options: [
                            'file' => $method->getFileName(),
                            'line' => $method->getStartLine(),
                        ]);
                        continue;
                    }

                    $instance = $attribute->newInstance();

                    self::$cache['commands'][$instance->name] = sprintf('%s::%s', $class, $method->name);
                }
            }
        }
    }
}
