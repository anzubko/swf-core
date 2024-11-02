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
use SWF\Attribute\AsConsumer;

/**
 * @internal
 */
final class ConsumerProcessor extends AbstractActionProcessor
{
    private const RELATIVE_CACHE_FILE = '/.system/consumers.php';

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
                    foreach ($rMethod->getAttributes(AsConsumer::class, ReflectionAttribute::IS_INSTANCEOF) as $rAttribute) {
                        if ($rMethod->isConstructor()) {
                            throw new LogicException("Constructor can't be a consumer");
                        }

                        /** @var AsConsumer $instance */
                        $instance = $rAttribute->newInstance();

                        foreach ($this->getTypes($rMethod) as $type) {
                            $consumer = ['callback' => sprintf('%s::%s', $rClass->name, $rMethod->name), 'type' => $type];

                            if ($instance->priority !== 0.0) {
                                $consumer['priority'] = $instance->priority;
                            }

                            $cache[] = $consumer;
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
        ConsumerStorage::$cache = $cache;
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
                    throw new LogicException('Intersection types have no sense for consumers');
                }
            }
        } elseif ($type instanceof ReflectionIntersectionType) {
            throw new LogicException('Intersection types have no sense for consumers');
        } elseif ($type === null) {
            throw new LogicException('Consumer must have first parameter with declared type');
        }

        return $names;
    }
}
