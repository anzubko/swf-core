<?php
declare(strict_types=1);

namespace SWF;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;
use SWF\Attribute\GetEnv;
use function array_key_exists;
use function is_array;

abstract class AbstractConfig
{
    public function __construct()
    {
        static $env;
        if (!isset($env)) {
            $files = [];
            if (isset($_SERVER['APP_ENV'])) {
                $files[] = sprintf('/.env.%s.local.php', $_SERVER['APP_ENV']);
                $files[] = sprintf('/.env.%s.php', $_SERVER['APP_ENV']);
            } else {
                $files[] = '/.env.local.php';
            }

            $files[] = '/.env.php';

            $env = $_SERVER;
            foreach ($files as $file) {
                $params = @include APP_DIR . $file;
                if (false !== $params) {
                    $env += $params;
                }
            }
        }

        foreach ((new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            foreach ($property->getAttributes(GetEnv::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                $key = $attribute->newInstance()->getKey();
                if (!array_key_exists($key, $env)) {
                    continue;
                }

                if (is_array($env[$key])) {
                    $this->{$property->name} = $env[$key] + ($this->{$property->name} ?? []);
                } else {
                    $this->{$property->name} = $env[$key];
                }
            }
        }
    }
}
