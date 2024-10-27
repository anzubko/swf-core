<?php
declare(strict_types=1);

namespace SWF;

use LogicException;
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
                    foreach ($rMethod->getAttributes(AsCommand::class) as $rAttribute) {
                        if ($rMethod->isConstructor()) {
                            throw new LogicException("Constructor can't be a command");
                        }

                        /** @var AsCommand $instance  */
                        $instance = $rAttribute->newInstance();

                        $command = ['method' => sprintf('%s::%s', $rClass->name, $rMethod->name)];

                        if (null !== $instance->getDescription()) {
                            $command['description'] = $instance->getDescription();
                        }

                        foreach ($instance->getParams() as $key => $param) {
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

                        $cache[$instance->getAlias()] = $command;
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
        if (null !== $param->getDescription()) {
            $argument['description'] = $param->getDescription();
        }
        if ($param->isArray()) {
            $argument['array'] = true;
        }
        if ($param->isRequired()) {
            $argument['required'] = true;
        }
        if (CommandTypeEnum::STRING !== $param->getType()) {
            $argument['type'] = $param->getType()->value;
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

        $name = $param->getName() ?? $key;
        if (in_array($name, self::RESTRICTED_OPTION_KEYS_NAMES)) {
            throw new LogicException(sprintf('Option name --%s is restricted for use', $name));
        }
        if (isset($command['optionNames'][$name])) {
            throw new LogicException(sprintf('Option name --%s already exists', $name));
        }

        $command['optionNames'][$name] = $key;

        if (null !== $param->getShortcut()) {
            if (mb_strlen($param->getShortcut()) !== 1) {
                throw new LogicException(sprintf('Malformed shortcut in option with key %s', $key));
            }
            if (in_array($param->getShortcut(), self::RESTRICTED_OPTION_SHORTCUTS)) {
                throw new LogicException(sprintf('Option shortcut -%s is restricted for use', $param->getShortcut()));
            }
            if (isset($command['optionShortcuts'][$param->getShortcut()])) {
                throw new LogicException(sprintf('Option shortcut -%s already exists', $param->getShortcut()));
            }

            $command['optionShortcuts'][$param->getShortcut()] = $key;
        }

        if (CommandValueEnum::NONE === $param->getValue() && CommandTypeEnum::BOOL !== $param->getType()) {
            throw new LogicException(sprintf('Type NONE can be used only with BOOL value type in option %s', $key));
        }

        if (null !== $param->getDescription()) {
            $option['description'] = $param->getDescription();
        }
        if ($param->isRequired()) {
            $option['required'] = true;
        }
        if ($param->isArray()) {
            $option['array'] = true;
        }
        if ($param->isHidden()) {
            $option['hidden'] = true;
        }
        if (CommandTypeEnum::STRING !== $param->getType()) {
            $option['type'] = $param->getType()->value;
        }
        if (CommandValueEnum::OPTIONAL !== $param->getValue()) {
            $option['value'] = $param->getValue()->value;
        }

        $command['options'][$key] = $option;

        return $command;
    }
}
