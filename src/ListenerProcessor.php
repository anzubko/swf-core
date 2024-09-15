<?php declare(strict_types=1);

namespace SWF;

use LogicException;
use SWF\Attribute\AsListener;
use function count;

final class ListenerProcessor extends AbstractActionProcessor
{
    protected string $relativeCacheFile = '/.swf/listeners.php';

    public function buildCache(ActionClasses $classes): ActionCache
    {
        $cache = new ActionCache(['listeners' => []]);

        foreach ($classes->list as $class) {
            foreach ($class->getMethods() as $method) {
                try {
                    foreach ($method->getAttributes(AsListener::class) as $attribute) {
                        if ($method->isConstructor()) {
                            throw new LogicException("Constructor can't be a listener");
                        }

                        $params = $method->getParameters();
                        $type = count($params) === 0 ? null : $params[0]->getType();
                        if (null === $type) {
                            throw new LogicException('Listener must have first parameter with declared type');
                        }

                        $instance = $attribute->newInstance();

                        $listener = [];

                        $listener['callback'] = sprintf('%s::%s', $class->name, $method->name);

                        $listener['type'] = (string) $type;

                        if ($instance->disposable) {
                            $listener['disposable'] = true;
                        }

                        if ($instance->persistent) {
                            $listener['persistent'] = true;
                        }

                        $listener['priority'] = $instance->priority;

                        $cache->data['listeners'][] = $listener;
                    }
                } catch (LogicException $e) {
                    throw ExceptionHandler::overrideFileAndLine($e, (string) $method->getFileName(), (int) $method->getStartLine());
                }
            }
        }

        usort($cache->data['listeners'], fn($a, $b) => $b['priority'] <=> $a['priority']);

        foreach (array_keys($cache->data['listeners']) as $i) {
            unset($cache->data['listeners'][$i]['priority']);
        }

        return $cache;
    }
}
