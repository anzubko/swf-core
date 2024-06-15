<?php declare(strict_types=1);

namespace SWF;

use ReflectionMethod;
use SWF\Attribute\AsCommand;

final class CommandProcessor extends AbstractActionProcessor
{
    protected string $cachePath = APP_DIR . '/var/cache/.swf/commands.php';

    public function buildCache(ActionClasses $classes): ActionCache
    {
        $cache = new ActionCache(['commands' => []]);

        foreach ($classes->list as $class) {
            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes(AsCommand::class) as $attribute) {
                    if ($method->isConstructor()) {
                        CommonLogger::getInstance()->warning("Constructor can't be a command", options: [
                            'file' => $method->getFileName(),
                            'line' => $method->getStartLine(),
                        ]);
                        continue;
                    }

                    $cache->data['commands'][$attribute->newInstance()->name] = sprintf('%s::%s', $class->name, $method->name);
                }
            }
        }

        return $cache;
    }
}
