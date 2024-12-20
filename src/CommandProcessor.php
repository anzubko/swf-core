<?php
declare(strict_types=1);

namespace SWF;

use LogicException;
use ReflectionAttribute;
use SWF\Attribute\AsCommand;
use SWF\Enum\CommandTypeEnum;
use SWF\Enum\CommandValueEnum;
use function in_array;

/**
 * @internal
 */
final class CommandProcessor extends AbstractActionProcessor
{
    private const RELATIVE_CACHE_FILE = '/.system/commands.php';

    private const RESTRICTED_OPTION_KEYS_NAMES = ['help', 'quiet'];

    private const RESTRICTED_OPTION_SHORTCUTS = ['h', 'q'];

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
                    foreach ($rMethod->getAttributes(AsCommand::class, ReflectionAttribute::IS_INSTANCEOF) as $rAttribute) {
                        if ($rMethod->isConstructor()) {
                            throw new LogicException("Constructor can't be a command");
                        }

                        /** @var AsCommand $instance  */
                        $instance = $rAttribute->newInstance();

                        $command = ['method' => sprintf('%s::%s', $rClass->name, $rMethod->name)];

                        if ($instance->description !== null) {
                            $command['description'] = $instance->description;
                        }

                        foreach ($instance->params as $key => $param) {
                            if (in_array($key, self::RESTRICTED_OPTION_KEYS_NAMES, true)) {
                                throw new LogicException(sprintf('Key %s is restricted for use', $key));
                            } elseif ($param instanceof CommandArgument) {
                                $command = $this->decomposeArgument($command, (string) $key, $param);
                            } elseif ($param instanceof CommandOption) {
                                $command = $this->decomposeOption($command, (string) $key, $param);
                            } else {
                                throw new LogicException('Command parameter must be instance of CommandArgument or CommandOption');
                            }
                        }

                        $cache[$instance->alias] = $command;
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
        CommandStorage::$cache = $cache;
    }

    /**
     * @param mixed[] $command
     *
     * @return mixed[]
     */
    private function decomposeArgument(array $command, string $key, CommandArgument $param): array
    {
        $argument = [];
        if ($param->description !== null) {
            $argument['description'] = $param->description;
        }
        if ($param->array) {
            $argument['array'] = true;
        }
        if ($param->required) {
            $argument['required'] = true;
        }
        if ($param->type !== CommandTypeEnum::STRING) {
            $argument['type'] = $param->type->value;
        }

        $command['arguments'][$key] = $argument;

        $command['argumentsIndex'][] = $key;

        return $command;
    }

    /**
     * @param mixed[] $command
     *
     * @return mixed[]
     *
     * @throws LogicException
     */
    private function decomposeOption(array $command, string $key, CommandOption $param): array
    {
        $option = [];

        $name = $param->name ?? $key;
        if (in_array($name, self::RESTRICTED_OPTION_KEYS_NAMES)) {
            throw new LogicException(sprintf('Option name --%s is restricted for use', $name));
        }
        if (isset($command['optionNames'][$name])) {
            throw new LogicException(sprintf('Option name --%s already exists', $name));
        }

        $command['optionNames'][$name] = $key;

        if ($param->shortcut !== null) {
            if (mb_strlen($param->shortcut) !== 1) {
                throw new LogicException(sprintf('Malformed shortcut in option with key %s', $key));
            }
            if (in_array($param->shortcut, self::RESTRICTED_OPTION_SHORTCUTS)) {
                throw new LogicException(sprintf('Option shortcut -%s is restricted for use', $param->shortcut));
            }
            if (isset($command['optionShortcuts'][$param->shortcut])) {
                throw new LogicException(sprintf('Option shortcut -%s already exists', $param->shortcut));
            }

            $command['optionShortcuts'][$param->shortcut] = $key;
        }

        if ($param->value === CommandValueEnum::NONE && $param->type !== CommandTypeEnum::BOOL) {
            throw new LogicException(sprintf('Type NONE can be used only with BOOL value type in option %s', $key));
        }

        if ($param->description !== null) {
            $option['description'] = $param->description;
        }
        if ($param->required) {
            $option['required'] = true;
        }
        if ($param->array) {
            $option['array'] = true;
        }
        if ($param->hidden) {
            $option['hidden'] = true;
        }
        if ($param->type !== CommandTypeEnum::STRING) {
            $option['type'] = $param->type->value;
        }
        if ($param->value !== CommandValueEnum::OPTIONAL) {
            $option['value'] = $param->value->value;
        }

        $command['options'][$key] = $option;

        return $command;
    }
}
