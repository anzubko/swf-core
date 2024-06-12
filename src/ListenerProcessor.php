<?php declare(strict_types=1);

namespace SWF;

use ReflectionMethod;
use SWF\Attribute\AsListener;
use function count;

final class ListenerProcessor extends AbstractActionProcessor
{
    protected string $cacheFile = APP_DIR . '/var/cache/.swf/actions/listeners.php';

    public function initializeCache(): ActionCache
    {
        return new ActionCache([
            'listeners' => [],
        ]);
    }

    public function processMethod(ActionCache $cache, ReflectionMethod $method): void
    {
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
                'callback' => sprintf('%s::%s', $method->class, $method->name),
                'type' => (string) $type,
                'disposable' => $instance->disposable,
                'persistent' => $instance->persistent,
                'priority' => $instance->priority,
            ];
        }
    }

    public function finalizeCache(ActionCache $cache): void
    {
        usort($cache->data['listeners'], fn($a, $b) => $a['priority'] <=> $b['priority']);

        foreach (array_keys($cache->data['listeners']) as $i) {
            unset($cache->data['listeners'][$i]['priority']);
        }
    }
}
