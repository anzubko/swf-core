<?php declare(strict_types=1);

namespace SWF;

use LogicException;
use RuntimeException;

final class RelationProvider
{
    private ?ActionCache $cache;

    /**
     * @throws LogicException
     * @throws RuntimeException
     */
    public function __construct()
    {
        $this->cache = i(ActionManager::class)->getCache(RelationProcessor::class);
    }

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
        return $this->cache?->data['relations'][$className] ?? [];
    }
}
