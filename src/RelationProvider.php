<?php declare(strict_types=1);

namespace SWF;

final class RelationProvider
{
    /**
     * Accesses child classes of some class/interface.
     *
     * @template T
     *
     * @param class-string<T> $className
     *
     * @return array<class-string<T>>
     */
    public function getChildren(string $className): array
    {
        return RelationStorage::$cache[$className] ?? [];
    }
}
