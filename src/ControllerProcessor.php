<?php
declare(strict_types=1);

namespace SWF;

use LogicException;
use ReflectionAttribute;
use SWF\Attribute\AsController;
use function count;
use function is_string;

/**
 * @internal
 */
final class ControllerProcessor extends AbstractActionProcessor
{
    private const RELATIVE_CACHE_FILE = '/.system/controllers.php';

    protected function getRelativeCacheFile(): string
    {
        return self::RELATIVE_CACHE_FILE;
    }

    public function buildCache(array $rClasses): array
    {
        $cache = ['static' => [], 'dynamic' => [], 'urls' => [], 'actions' => []];

        foreach ($rClasses as $rClass) {
            foreach ($rClass->getMethods() as $rMethod) {
                try {
                    foreach ($rMethod->getAttributes(AsController::class, ReflectionAttribute::IS_INSTANCEOF) as $rAttribute) {
                        if ($rMethod->isConstructor()) {
                            throw new LogicException("Constructor can't be a controller");
                        }

                        /** @var AsController $instance  */
                        $instance = $rAttribute->newInstance();
                        foreach ((array) $instance->url as $url) {
                            $httpMethods = (array) $instance->method;
                            if (count($httpMethods) === 0) {
                                $httpMethods[] = 'ANY';
                            }

                            foreach ($httpMethods as $httpMethod) {
                                $method = sprintf('%s::%s', $rClass->name, $rMethod->name);
                                if ($instance->alias !== null) {
                                    $cache['static'][$url][strtoupper($httpMethod)] = [$method, $instance->alias];
                                } else {
                                    $cache['static'][$url][strtoupper($httpMethod)] = $method;
                                }
                            }
                        }
                    }
                } catch (LogicException $e) {
                    throw ExceptionHandler::overrideFileAndLine($e, (string) $rMethod->getFileName(), (int) $rMethod->getStartLine());
                }
            }
        }

        $regex = [];
        foreach ($cache['static'] as $url => $actions) {
            if (preg_match_all('/{([^}]+)}/', $url, $M)) {
                unset($cache['static'][$url]);

                $regex[] = sprintf('%s(*:%d)', preg_replace('/\\\\{[^}]+}/', '([^/]+)', preg_quote($url)), count($cache['dynamic']));

                $cache['dynamic'][] = [$actions, $M[1]];

                $cache['urls'][] = preg_split('/({[^}]+})/', $url, flags: PREG_SPLIT_DELIM_CAPTURE);

                $paramsCount = count($M[1]);
            } else {
                $cache['urls'][] = $url;

                $paramsCount = 0;
            }

            foreach ($actions as $action) {
                if (is_string($action)) {
                    $action = [$action, null];
                }

                foreach ($action as $name) {
                    if ($name !== null) {
                        $cache['actions'][sprintf('%s:%s', $name, $paramsCount)] = count($cache['urls']) - 1;
                    }
                }
            }
        }

        $cache['regex'] = sprintf('{^(?|%s)$}', implode('|', $regex));

        return $cache;
    }

    public function storageCache(array $cache): void
    {
        ControllerStorage::$cache = $cache;
    }
}
