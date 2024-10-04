<?php declare(strict_types=1);

namespace SWF;

use LogicException;
use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use SWF\Attribute\AsListener;

/**
 * @internal
 */
final class ListenerProcessor extends AbstractActionProcessor
{
    private const RELATIVE_CACHE_FILE = '/.system/listeners.php';

    protected function getRelativeCacheFile(): string
    {
        return self::RELATIVE_CACHE_FILE;
    }

    public function buildCache(array $classes): array
    {
        $cache = [];
        foreach ($classes as $class) {
            foreach ($class->getMethods() as $method) {
                try {
                    foreach ($method->getAttributes(AsListener::class) as $attribute) {
                        if ($method->isConstructor()) {
                            throw new LogicException("Constructor can't be a listener");
                        }

                        $instance = $attribute->newInstance();

                        foreach ($this->getTypes($method) as $type) {
                            $listener = ['callback' => sprintf('%s::%s', $class->name, $method->name), 'type' => $type];

                            if (0.0 !== $instance->priority) {
                                $listener['priority'] = $instance->priority;
                            }
                            if ($instance->disposable) {
                                $listener['disposable'] = true;
                            }
                            if ($instance->persistent) {
                                $listener['persistent'] = true;
                            }

                            $cache[] = $listener;
                        }
                    }
                } catch (LogicException $e) {
                    throw ExceptionHandler::overrideFileAndLine($e, (string) $method->getFileName(), (int) $method->getStartLine());
                }
            }
        }

        return $cache;
    }

    public function putCacheToStorage(array $cache): void
    {
        ListenerStorage::$cache = $cache;
    }

    /**
     * @return string[]
     *
     * @throws LogicException
     */
    public function getTypes(ReflectionFunction|ReflectionMethod $reflection): array
    {
        $type = ($reflection->getParameters()[0] ?? null)?->getType();

        $names = [];
        if ($type instanceof ReflectionNamedType) {
            $names[] = $type->getName();
        } elseif ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $subType) {
                if ($subType instanceof ReflectionNamedType) {
                    $names[] = $subType->getName();
                } elseif ($subType instanceof ReflectionIntersectionType) {
                    throw new LogicException('Intersection types have no sense for listeners');
                }
            }
        } elseif ($type instanceof ReflectionIntersectionType) {
            throw new LogicException('Intersection types have no sense for listeners');
        } elseif (null === $type) {
            throw new LogicException('Listener must have first parameter with declared type');
        }

        return $names;
    }
}
