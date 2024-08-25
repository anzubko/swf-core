<?php declare(strict_types=1);

namespace SWF;

use LogicException;
use ReflectionMethod;
use SWF\Attribute\AsCommand;
use SWF\Enum\CommandTypeEnum;
use SWF\Enum\CommandValueEnum;
use Throwable;

final class CommandProcessor extends AbstractActionProcessor
{
    protected string $cacheFile = APP_DIR . '/var/cache/.swf/commands.php';

    public function buildCache(ActionClasses $classes): ActionCache
    {
        $cache = new ActionCache(['commands' => []]);

        foreach ($classes->list as $class) {
            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                try {
                    foreach ($method->getAttributes(AsCommand::class) as $attribute) {
                        if ($method->isConstructor()) {
                            throw new LogicException("Constructor can't be a command");
                        }

                        $instance = $attribute->newInstance();

                        $command = [];

                        $command['name'] = sprintf('%s::%s', $class->name, $method->name);

                        if (null !== $instance->description) {
                            $command['description'] = $instance->description;
                        }

                        foreach ($instance->params as $key => $param) {
                            if ($param instanceof CommandArgument) {
                                $command = $this->decomposeArgument($command, $key, $param);
                            } elseif ($param instanceof CommandOption) {
                                $command = $this->decomposeOption($command, $key, $param);
                            } else {
                                throw new LogicException('Command parameter must be instance of CommandArgument or CommandOption');
                            }
                        }

                        $cache->data['commands'][$instance->name] = $command;
                    }
                } catch (Throwable $e) {
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
            $argument['type'] = $param->type;
        }

        $command['arguments'][$key] = $argument;

        $command['argumentsIndex'][] = $key;

        return $command;
    }

    /**
     * @param mixed[] $command
     *
     * @return mixed[]
     */
    private function decomposeOption(array $command, string $key, CommandOption $param): array
    {
        $option = [];

        $name = $param->name ?? $key;
        if (isset($command['optionNames'][$name])) {
            throw new LogicException(sprintf('Duplicate name --%s in option %s', $name, $key));
        }

        $command['optionNames'][$name] = $key;

        if (null !== $param->shortcut) {
            for ($i = 0, $length = mb_strlen($param->shortcut); $i < $length; $i++) {
                $shortcut = mb_substr($param->shortcut, $i, 1);
                if (isset($command['optionShortcuts'][$shortcut])) {
                    throw new LogicException(sprintf('Duplicate shortcut -%s in option %s', $shortcut, $key));
                }

                $command['optionShortcuts'][$shortcut] = $key;
            }
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
            $option['type'] = $param->type;
        }

        if (CommandValueEnum::OPTIONAL !== $param->value) {
            $option['value'] = $param->value;
        }

        $command['options'][$key] = $option;

        return $command;
    }
}
