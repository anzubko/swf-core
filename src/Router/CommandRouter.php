<?php declare(strict_types=1);

namespace SWF\Router;

use Psr\Log\LogLevel;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use SWF\Attribute\AsCommand;
use SWF\AbstractRouter;
use SWF\CommonLogger;
use SWF\ConfigHolder;

final class CommandRouter extends AbstractRouter
{
    protected static array $cache;

    private static self $instance;

    /**
     * @throws RuntimeException
     */
    private function __construct()
    {
        $this->readCache(sprintf('%s/commands.php', ConfigHolder::get()->sysCacheDir));
    }

    /**
     * @throws RuntimeException
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Gets current action.
     *
     * Very poor implementation. Will be better soon.
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
            if (!str_starts_with($class, $this->appNs)) {
                continue;
            }

            $rClass = new ReflectionClass($class);
            foreach ($rClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes(AsCommand::class) as $attribute) {
                    if ($method->isConstructor()) {
                        CommonLogger::getInstance()->log(LogLevel::WARNING, "Constructor can't be a command", options: [
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
