<?php
declare(strict_types=1);

namespace SWF;

use ReflectionAttribute;
use SWF\Attribute\SetPriority;

/**
 * @internal
 */
final class SubclassProcessor extends AbstractActionProcessor
{
    private const RELATIVE_CACHE_FILE = '/.system/subclasses.php';

    protected function getRelativeCacheFile(): string
    {
        return self::RELATIVE_CACHE_FILE;
    }

    public function buildCache(array $rClasses): array
    {
        $cache = $priorities = [];
        foreach ($rClasses as $rClass) {
            foreach ($rClass->getInterfaceNames() as $interface) {
                $cache[$interface][] = $rClass->name;
            }

            for ($rClassParent = $rClass->getParentClass(); false !== $rClassParent; $rClassParent = $rClassParent->getParentClass()) {
                $cache[$rClassParent->name][] = $rClass->name;
            }

            foreach ($rClass->getAttributes(SetPriority::class, ReflectionAttribute::IS_INSTANCEOF) as $rAttribute) {
                $priorities[$rClass->name] = $rAttribute->newInstance()->getPriority();
            }
        }

        foreach ($cache as $class => $children) {
            usort($children, fn($a, $b) => ($priorities[$b] ?? 0) <=> ($priorities[$a] ?? 0));

            $cache[$class] = $children;
        }

        return $cache;
    }

    public function storageCache(array $cache): void
    {
        SubclassStorage::$cache = $cache;
    }
}
