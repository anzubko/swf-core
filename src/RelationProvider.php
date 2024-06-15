<?php declare(strict_types=1);

namespace SWF;

use LogicException;
use RuntimeException;

final class RelationProvider
{
    private static ActionCache $cache;

    private static self $instance;

    /**
     * @throws LogicException
     * @throws RuntimeException
     */
    private function __construct()
    {
        self::$cache = ActionManager::getInstance()->getCache(RelationProcessor::class);
    }

    /**
     * @throws LogicException
     * @throws RuntimeException
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
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
        return self::$cache->data['relations'][$className] ?? [];
    }
}
