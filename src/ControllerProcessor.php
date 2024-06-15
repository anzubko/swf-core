<?php declare(strict_types=1);

namespace SWF;

use ReflectionMethod;
use SWF\Attribute\AsController;
use function count;
use function is_string;

final class ControllerProcessor extends AbstractActionProcessor
{
    protected string $cachePath = APP_DIR . '/var/cache/.swf/controllers.php';

    public function buildCache(ActionClasses $classes): ActionCache
    {
        $cache = new ActionCache([
            'static' => [],
            'dynamic' => [],
            'urls' => [],
            'actions' => [],
        ]);

        foreach ($classes->list as $class) {
            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
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
                                $cache->data['static'][$url][$m] = [sprintf('%s::%s', $class->name, $method->name), $instance->alias];
                            } else {
                                $cache->data['static'][$url][$m] = sprintf('%s::%s', $class->name, $method->name);
                            }
                        }
                    }
                }
            }
        }

        $regex = [];
        foreach ($cache->data['static'] as $url => $actions) {
            if (preg_match_all('/{([^}]+)}/', $url, $M)) {
                unset($cache->data['static'][$url]);

                $regex[] = sprintf('%s(*:%d)', preg_replace('/\\\\{[^}]+}/', '([^/]+)', preg_quote($url)), count($cache->data['dynamic']));

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

        return  $cache;
    }
}
