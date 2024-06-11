<?php declare(strict_types=1);

namespace SWF;

use ReflectionMethod;
use SWF\Attribute\AsController;
use function count;
use function is_string;

final class ControllerProcessor extends AbstractActionProcessor
{
    protected string $cacheFile = APP_DIR . '/var/cache/.swf/controllers.php';

    public function initializeCache(): ActionCache
    {
        return new ActionCache([
            'static' => [],
            'dynamic' => [],
            'urls' => [],
            'actions' => [],
        ]);
    }

    public function processMethod(ActionCache $cache, ReflectionMethod $method): void
    {
        foreach ($method->getAttributes(AsController::class) as $attribute) {
            if ($method->isConstructor()) {
                CommonLogger::getInstance()->warning("Constructor can't be a controller", options: [
                    'file' => $method->getFileName(),
                    'line' => $method->getStartLine(),
                ]);
                continue;
            }

            $instance = $attribute->newInstance();
            foreach ($instance->url as $url) {
                foreach ($instance->method as $m) {
                    if (null !== $instance->alias) {
                        $cache->data['static'][$url][$m] = [sprintf('%s::%s', $method->class, $method->name), $instance->alias];
                    } else {
                        $cache->data['static'][$url][$m] = sprintf('%s::%s', $method->class, $method->name);
                    }
                }
            }
        }
    }

    public function finalizeCache(ActionCache $cache): void
    {
        $regex = [];
        foreach ($cache->data['static'] as $url => $actions) {
            if (preg_match_all('/{([^}]+)}/', $url, $M)) {
                unset($cache->data['static'][$url]);

                $regex[] = sprintf(
                    '%s(*:%d)',
                    preg_replace('/\\\\{[^}]+}/', '([^/]+)', preg_quote($url)),
                    count($cache->data['dynamic']),
                );

                $cache->data['dynamic'][] = [$actions, $M[1]];

                $cache->data['urls'][] = preg_split('/({[^}]+})/', $url, flags: PREG_SPLIT_DELIM_CAPTURE);

                $pCount = count($M[1]);
            } else {
                $cache->data['urls'][] = $url;

                $pCount = 0;
            }

            foreach ($actions as $action) {
                if (is_string($action)) {
                    $action = [$action, null];
                }

                foreach ($action as $name) {
                    if (null !== $name) {
                        $cache->data['actions'][sprintf('%s:%s', $name, $pCount)] = count($cache->data['urls']) - 1;
                    }
                }
            }
        }

        $cache->data['regex'] = sprintf('{^(?|%s)$}', implode('|', $regex));
    }
}
