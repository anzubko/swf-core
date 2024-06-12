<?php declare(strict_types=1);

namespace SWF;

use ReflectionMethod;
use SWF\Attribute\AsCommand;

final class CommandProcessor extends AbstractActionProcessor
{
    protected string $cacheFile = APP_DIR . '/var/cache/.swf/actions/commands.php';

    public function initializeCache(): ActionCache
    {
        return new ActionCache([
            'commands' => [],
        ]);
    }

    public function processMethod(ActionCache $cache, ReflectionMethod $method): void
    {
        foreach ($method->getAttributes(AsCommand::class) as $attribute) {
            if ($method->isConstructor()) {
                CommonLogger::getInstance()->warning("Constructor can't be a command", options: [
                    'file' => $method->getFileName(),
                    'line' => $method->getStartLine(),
                ]);
                continue;
            }

            $cache->data['commands'][$attribute->newInstance()->name] = sprintf('%s::%s', $method->class, $method->name);
        }
    }

    public function finalizeCache(ActionCache $cache): void
    {
    }
}
