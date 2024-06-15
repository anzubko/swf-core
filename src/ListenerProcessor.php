<?php declare(strict_types=1);

namespace SWF;

use ReflectionMethod;
use SWF\Attribute\AsListener;
use function count;

final class ListenerProcessor extends AbstractActionProcessor
{
    protected string $cacheFile = APP_DIR . '/var/cache/.swf/listeners.php';

    public function buildCache(ActionClasses $classes): ActionCache
    {
        $cache = new ActionCache(['listeners' => []]);

        foreach ($classes->list as $class) {
            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes(AsListener::class) as $attribute) {
                    if ($method->isConstructor()) {
                        CommonLogger::getInstance()->warning("Constructor can't be a listener", options: [
                            'file' => $method->getFileName(),
                            'line' => $method->getStartLine(),
                        ]);
                        continue;
                    }

                    $params = $method->getParameters();
                    $type = count($params) === 0 ? null : $params[0]->getType();
                    if (null === $type) {
                        CommonLogger::getInstance()->warning('Listener must have first parameter with declared type', options: [
                            'file' => $method->getFileName(),
                            'line' => $method->getStartLine(),
                        ]);
                        continue;
                    }

                    $instance = $attribute->newInstance();
                    $cache->data['listeners'][] = [
                        'callback' => sprintf('%s::%s', $class->name, $method->name),
                        'type' => (string) $type,
                        'disposable' => $instance->disposable,
                        'persistent' => $instance->persistent,
                        'priority' => $instance->priority,
                    ];
                }
            }
        }

        usort($cache->data['listeners'], fn($a, $b) => $a['priority'] <=> $b['priority']);

        foreach (array_keys($cache->data['listeners']) as $i) {
            unset($cache->data['listeners'][$i]['priority']);
        }

        return $cache;
    }
}
