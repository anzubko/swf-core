<?php declare(strict_types=1);

namespace SWF;

abstract class AbstractBase
{
    /**
     * @var array<class-string<AbstractShared>, object>
     */
    private static array $shared = [];

    /**
     * Accesses shared classes from this class extenders.
     *
     * @param class-string<AbstractShared> $className
     */
    final public function s(string $className): mixed
    {
        return self::$shared[$className] ??= $this->getShared(new $className)->getInstance();
    }

    private function getShared(AbstractShared $class): AbstractShared
    {
        return $class;
    }
}
