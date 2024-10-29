<?php
declare(strict_types=1);

namespace SWF;

use LogicException;
use ReflectionAttribute;
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

    public function buildCache(array $rClasses): array
    {
        $cache = [];
        foreach ($rClasses as $rClass) {
            foreach ($rClass->getMethods() as $rMethod) {
                try {
                    foreach ($rMethod->getAttributes(AsListener::class, ReflectionAttribute::IS_INSTANCEOF) as $rAttribute) {
                        if ($rMethod->isConstructor()) {
                            throw new LogicException("Constructor can't be a listener");
                        }

                        /** @var AsListener $instance */
                        $instance = $rAttribute->newInstance();

                        foreach ($this->getTypes($rMethod) as $type) {
                            $listener = ['callback' => sprintf('%s::%s', $rClass->name, $rMethod->name), 'type' => $type];

                            if (0.0 !== $instance->getPriority()) {
                                $listener['priority'] = $instance->getPriority();
                            }
                            if ($instance->isDisposable()) {
                                $listener['disposable'] = true;
                            }
                            if ($instance->isPersistent()) {
                                $listener['persistent'] = true;
                            }

                            $cache[] = $listener;
                        }
                    }
                } catch (LogicException $e) {
                    throw ExceptionHandler::overrideFileAndLine($e, (string) $rMethod->getFileName(), (int) $rMethod->getStartLine());
                }
            }
        }

        return $cache;
    }

    public function storageCache(array $cache): void
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
