<?php declare(strict_types=1);

namespace SWF;

use SWF\Attribute\Priority;

/**
 * @internal
 */
final class RelationProcessor extends AbstractActionProcessor
{
    private const RELATIVE_CACHE_FILE = '/.system/relations.php';

    protected function getRelativeCacheFile(): string
    {
        return self::RELATIVE_CACHE_FILE;
    }

    public function buildCache(array $classes): array
    {
        $cache = $priorities = [];
        foreach ($classes as $class) {
            foreach ($class->getInterfaceNames() as $interfaceName) {
                $cache[$interfaceName][] = $class->name;
            }

            for ($parentClass = $class->getParentClass(); false !== $parentClass; $parentClass = $parentClass->getParentClass()) {
                $cache[$parentClass->name][] = $class->name;
            }

            foreach ($class->getAttributes(Priority::class) as $attribute) {
                $priorities[$class->name] = $attribute->newInstance()->priority;
            }
        }

        foreach ($cache as $className => $childrenNames) {
            usort($childrenNames, fn($a, $b) => ($priorities[$b] ?? 0) <=> ($priorities[$a] ?? 0));

            $cache[$className] = $childrenNames;
        }

        return $cache;
    }

    public function putCacheToStorage(array $cache): void
    {
        RelationStorage::$cache = $cache;
    }
}
