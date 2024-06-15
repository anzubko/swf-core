<?php declare(strict_types=1);

namespace SWF;

use ReflectionMethod;

final class RelationProcessor extends AbstractActionProcessor
{
    protected string $cachePath = APP_DIR . '/var/cache/.swf/relations.php';

    public function buildCache(ActionClasses $classes): ActionCache
    {
        $cache = new ActionCache(['relations' => []]);

        foreach ($classes->list as $class) {
            foreach ($class->getInterfaceNames() as $interfaceName) {
                $cache->data['relations'][$interfaceName][] = $class->name;
            }

            $parentClass = $class;
            while (false !== ($parentClass = $parentClass->getParentClass())) {
                $cache->data['relations'][$parentClass->name][] = $class->name;
            }
        }

        return $cache;
    }
}
