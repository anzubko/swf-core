<?php
declare(strict_types=1);

namespace SWF;

use Exception;
use InvalidArgumentException;
use SWF\Enum\ActionTypeEnum;
use SWF\Enum\CommandValueEnum;
use function array_key_exists;
use function count;
use function strlen;

/**
 * @internal
 */
final class CommandProvider
{
    private ?string $alias;

    private ?CommandDefinition $command = null;

    public function __construct()
    {
        $this->alias = $_SERVER['argv'][1] ?? null;
        if ($this->alias === null || !array_key_exists($this->alias, CommandStorage::$cache)) {
            return;
        }

        $this->command = i(CommandHelper::class)->arrayToCommandDefinition($this->alias, CommandStorage::$cache[$this->alias]);
    }

    /**
     * @throws Exception
     */
    public function getCurrentAction(): CurrentAction
    {
        if ($this->alias === null) {
            return new CurrentAction(ActionTypeEnum::COMMAND, implode('::', [CommandUtil::class, 'listAll']));
        }

        if ($this->command === null) {
            return new CurrentAction(ActionTypeEnum::COMMAND, alias: $this->alias);
        }

        $paramsParser = new CommandParamsParser($this->command);

        try {
            for ($i = 0, $chunks = array_slice($_SERVER['argv'], 2); $i < count($chunks); $i++) {
                $chunk = $chunks[$i];
                if (strlen($chunk) > 2 && $chunk[0] === '-' && $chunk[1] === '-') {
                    $needHelp = $paramsParser->processOption($chunk);
                } elseif (strlen($chunk) > 1 && $chunk[0] === '-' && $chunk[1] !== '-') {
                    $needHelp = $paramsParser->processShortOption($i, $chunks);
                } else {
                    $needHelp = $paramsParser->processArgument($chunk);
                }

                if ($needHelp) {
                    return new CurrentAction(ActionTypeEnum::COMMAND, implode('::', [self::class, 'showHelp']), $this->alias);
                }
            }

            $paramsParser->checkForRequires();
        } catch (InvalidArgumentException $e) {
            $usage = $this->genUsage();
            if ($usage !== null) {
                CommandLineManager::writeLn(sprintf("Usage:\n  %s\n", $usage));
            }

            CommandLineManager::error($e->getMessage());
        }

        CommandLineManager::setQuiet($_REQUEST['quiet'] ?? false);

        return new CurrentAction(ActionTypeEnum::COMMAND, $this->command->method, $this->alias);
    }

    public function showHelp(): void
    {
        if ($this->command === null) {
            return;
        }

        $maxLength = 0;
        $arguments = $options = [];
        foreach ($this->command->arguments as $key => $argument) {
            $arguments[$key] = (string) $key;
            $maxLength = max($maxLength, mb_strlen((string) $key));
        }

        foreach ($this->command->options as $key => $option) {
            if ($option->hidden) {
                continue;
            }
            if ($option->shortcut === null) {
                $chunk = sprintf('    --%s', $option->name);
            } else {
                $chunk = sprintf('-%s, --%s', $option->shortcut, $option->name);
            }

            $options[$key] = $chunk;
            $maxLength = max($maxLength, mb_strlen($chunk));
        }

        foreach ($arguments as $key => $argument) {
            if ($this->command->arguments[$key]->description !== null) {
                $arguments[$key] = mb_str_pad($argument, $maxLength + 2) . $this->command->arguments[$key]->description;
            }
        }

        foreach ($options as $key => $option) {
            if ($this->command->options[$key]->description !== null) {
                $options[$key] = mb_str_pad($option, $maxLength + 2) . $this->command->options[$key]->description;
            }
        }

        if ($this->command->description !== null) {
            CommandLineManager::write(sprintf("Description:\n  %s\n\n", $this->command->description));
        }

        CommandLineManager::write(sprintf("Usage:\n  %s\n", $this->genUsage()));

        if (count($arguments) > 0) {
            CommandLineManager::write(sprintf("\nArguments:\n  %s\n", implode("\n  ", $arguments)));
        }

        if (count($options) > 0) {
            CommandLineManager::write(sprintf("\nOptions:\n  %s\n", implode("\n  ", $options)));
        }
    }

    private function genUsage(): ?string
    {
        if ($this->command === null) {
            return null;
        }

        $argumentsUsage = $optionsUsage = [];
        foreach ($this->command->arguments as $key => $argument) {
            $chunk = sprintf('<%s:%s>', $key, $argument->type->name);
            if ($argument->array) {
                $chunk = sprintf('%s...', $chunk);
            }
            if (!$argument->required) {
                $chunk = sprintf('[%s]', $chunk);
            }

            $argumentsUsage[] = $chunk;
        }

        foreach ($this->command->options as $option) {
            if ($option->shortcut === null) {
                $chunk = sprintf('--%s', $option->name);
            } else {
                $chunk = sprintf('-%s|--%s', $option->shortcut, $option->name);
            }
            if ($option->value === CommandValueEnum::REQUIRED) {
                $chunk = sprintf('%s=%s', $chunk, $option->type->name);
            } elseif ($option->value === CommandValueEnum::OPTIONAL) {
                $chunk = sprintf('%s[=%s]', $chunk, $option->value->name);
            }
            if ($option->array) {
                $chunk = sprintf('%s...', $chunk);
            }
            if (!$option->required) {
                $chunk = sprintf('[%s]', $chunk);
            }

            $optionsUsage[] = $chunk;
        }

        return implode(' ', [$this->alias, ...$optionsUsage, ...$argumentsUsage]);
    }
}
