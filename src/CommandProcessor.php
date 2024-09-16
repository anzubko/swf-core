<?php declare(strict_types=1);

namespace SWF;

use LogicException;
use SWF\Attribute\AsCommand;
use SWF\Enum\CommandTypeEnum;
use SWF\Enum\CommandValueEnum;
use function in_array;

final class CommandProcessor extends AbstractActionProcessor
{
    private const RESTRICTED_KEYS = ['help'];
    private const RESTRICTED_OPTION_NAMES = ['help'];
    private const RESTRICTED_OPTION_SHORTCUTS = ['h'];

    protected string $relativeCacheFile = '/.system/commands.php';

    public function buildCache(ActionClasses $classes): ActionCache
    {
        $cache = new ActionCache(['commands' => []]);

        foreach ($classes->list as $class) {
            foreach ($class->getMethods() as $method) {
                try {
                    foreach ($method->getAttributes(AsCommand::class) as $attribute) {
                        if ($method->isConstructor()) {
                            throw new LogicException("Constructor can't be a command");
                        }

                        $instance = $attribute->newInstance();

                        $command = ['action' => sprintf('%s::%s', $class->name, $method->name)];

                        if (null !== $instance->description) {
                            $command['description'] = $instance->description;
                        }

                        foreach ($instance->params as $key => $param) {
                            if (in_array($key, self::RESTRICTED_KEYS, true)) {
                                throw new LogicException(sprintf('Key %s is restricted for use', $key));
                            } elseif ($param instanceof CommandArgument) {
                                $command = $this->decomposeArgument($command, (string) $key, $param);
                            } elseif ($param instanceof CommandOption) {
                                $command = $this->decomposeOption($command, (string) $key, $param);
                            } else {
                                throw new LogicException('Command parameter must be instance of CommandArgument or CommandOption');
                            }
                        }

                        $cache->data['commands'][$instance->alias] = $command;
                    }
                } catch (LogicException $e) {
                    throw ExceptionHandler::overrideFileAndLine($e, (string) $method->getFileName(), (int) $method->getStartLine());
                }
            }
        }

        return $cache;
    }

    /**
     * @param mixed[] $command
     *
     * @return mixed[]
     */
    private function decomposeArgument(array $command, string $key, CommandArgument $param): array
    {
        $argument = [];
        if (null !== $param->description) {
            $argument['description'] = $param->description;
        }
        if ($param->isArray) {
            $argument['isArray'] = true;
        }
        if ($param->isRequired) {
            $argument['isRequired'] = true;
        }
        if (CommandTypeEnum::STRING !== $param->type) {
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
        if (in_array($name, self::RESTRICTED_OPTION_NAMES)) {
            throw new LogicException(sprintf('Option name --%s is restricted for use', $name));
        }
        if (isset($command['optionNames'][$name])) {
            throw new LogicException(sprintf('Option name --%s already exists', $name));
        }

        $command['optionNames'][$name] = $key;

        if (null !== $param->shortcut) {
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

        if (CommandValueEnum::NONE === $param->value && CommandTypeEnum::BOOL !== $param->type) {
            throw new LogicException(sprintf('Type NONE can be used only with BOOL value type in option %s', $key));
        }

        if (null !== $param->description) {
            $option['description'] = $param->description;
        }
        if ($param->isRequired) {
            $option['isRequired'] = true;
        }
        if ($param->isArray) {
            $option['isArray'] = true;
        }
        if (CommandTypeEnum::STRING !== $param->type) {
            $option['type'] = $param->type->value;
        }
        if (CommandValueEnum::OPTIONAL !== $param->value) {
            $option['value'] = $param->value->value;
        }

        $command['options'][$key] = $option;

        return $command;
    }
}
