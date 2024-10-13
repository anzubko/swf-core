<?php
declare(strict_types=1);

namespace SWF;

final class SubclassProvider
{
    /**
     * Accesses subclasses of some class/interface.
     *
     * @template T
     *
     * @param class-string<T> $class
     *
     * @return array<class-string<T>>
     */
    public function get(string $class): array
    {
        return SubclassStorage::$cache[$class] ?? [];
    }

    /**
     * Accesses subclasses instances of some class/interface.
     *
     * @template T
     *
     * @param class-string<T> $class
     *
     * @return iterable<T>
     */
    public function getInstances(string $class): iterable
    {
        foreach ($this->get($class) as $subclass) {
            yield i($subclass);
        }
    }
}
