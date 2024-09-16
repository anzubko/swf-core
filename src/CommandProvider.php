<?php declare(strict_types=1);

namespace SWF;

use InvalidArgumentException;
use LogicException;
use RuntimeException;
use SWF\Enum\ActionTypeEnum;
use SWF\Enum\CommandValueEnum;
use function count;
use function strlen;

final class CommandProvider
{
    private ?ActionCache $cache;

    private ?string $alias = null;

    private ?CommandDefinition $command = null;

    /**
     * @throws LogicException
     * @throws RuntimeException
     */
    public function __construct()
    {
        $this->cache = i(ActionManager::class)->getCache(CommandProcessor::class);
        if (null == $this->cache) {
            return;
        }

        $this->alias = $_SERVER['argv'][1] ?? null;

        if (isset($this->cache->data['commands'][$this->alias])) {
            $this->command = i(CommandHelper::class)->arrayToCommandDefinition($this->cache->data['commands'][$this->alias]);
        }
    }

    /**
     * Gets current action.
     */
    public function getCurrentAction(): ?CurrentActionInfo
    {
        if (null === $this->cache) {
            return null;
        }

        if (null === $this->alias) {
            return new CurrentActionInfo(ActionTypeEnum::COMMAND, implode('::', [self::class, 'listAll']), $this->alias);
        }

        if (null === $this->command) {
            return null;
        }

        $paramsParser = new CommandParamsParser($this->command);

        try {
            for ($i = 0, $chunks = array_slice($_SERVER['argv'], 2); $i < count($chunks); $i++) {
                $chunk = $chunks[$i];
                if (strlen($chunk) > 2 && '-' === $chunk[0] && '-' === $chunk[1]) {
                    $needHelp = $paramsParser->processOption($chunk);
                } elseif (strlen($chunk) > 1 && '-' === $chunk[0] && '-' !== $chunk[1]) {
                    $needHelp = $paramsParser->processShortOption($i, $chunks);
                } else {
                    $needHelp = $paramsParser->processArgument($chunk);
                }

                if ($needHelp) {
                    return new CurrentActionInfo(ActionTypeEnum::COMMAND, implode('::', [self::class, 'showHelp']), $this->alias);
                }
            }

            $paramsParser->checkForRequires();
        } catch (InvalidArgumentException $e) {
            $usage = $this->genUsage();
            if (null === $usage) {
                echo sprintf("Error: %s\n", $e->getMessage());
            } else {
                echo sprintf("Usage:\n  %s\n\nError: %s\n", $this->genUsage(), $e->getMessage());
            }

            exit(1);
        }

        return new CurrentActionInfo(ActionTypeEnum::COMMAND, $this->command->action, $this->alias);
    }

    /**
     * @internal
     */
    public function listAll(): void
    {
        if (null === $this->cache) {
            return;
        }

        $commands = $this->cache->data['commands'];
        if (count($commands) === 0) {
            echo "No commands found.\n";
            return;
        }

        echo "Available commands:\n";

        ksort($commands);
        foreach ($commands as $name => $command) {
            echo sprintf("\n%s --> %s\n", $name, $command['action']);

            if (isset($command['description'])) {
                echo sprintf("  %s\n", $command['description']);
            }
        }

        echo "\n";
    }

    private function showHelp(): void
    {
        if (null === $this->command) {
            return;
        }

        $maxLength = 0;
        $arguments = $options = [];

        foreach ($this->command->arguments as $key => $argument) {
            $arguments[$key] = (string) $key;
            $maxLength = max($maxLength, mb_strlen((string) $key));
        }

        foreach ($this->command->options as $key => $option) {
            if (null === $option->shortcut) {
                $chunk = sprintf('    --%s', $option->name);
            } else {
                $chunk = sprintf('-%s, --%s', $option->shortcut, $option->name);
            }

            $options[$key] = $chunk;
            $maxLength = max($maxLength, mb_strlen($chunk));
        }

        foreach ($arguments as $key => $argument) {
            if (null !== $this->command->arguments[$key]->description) {
                $arguments[$key] = mb_str_pad($argument, $maxLength + 2) . $this->command->arguments[$key]->description;
            }
        }

        foreach ($options as $key => $option) {
            if (null !== $this->command->options[$key]->description) {
                $options[$key] = mb_str_pad($option, $maxLength + 2) . $this->command->options[$key]->description;
            }
        }

        if (null !== $this->command->description) {
            echo sprintf("Description:\n  %s\n\n", $this->command->description);
        }

        echo sprintf("Usage:\n  %s\n", $this->genUsage(false));

        if (count($arguments) > 0) {
            echo sprintf("\nArguments:\n  %s\n", implode("\n  ", $arguments));
        }

        if (count($options) > 0) {
            echo sprintf("\nOptions:\n  %s\n", implode("\n  ", $options));
        }
    }

    private function genUsage(bool $withHelp = true): ?string
    {
        if (null === $this->command) {
            return null;
        }

        $argumentsUsage = $optionsUsage = [];

        foreach ($this->command->arguments as $key => $argument) {
            $chunk = sprintf('<%s:%s>', $key, $argument->type->name);
            if ($argument->isArray) {
                $chunk = sprintf('%s...', $chunk);
            }
            if (!$argument->isRequired) {
                $chunk = sprintf('[%s]', $chunk);
            }

            $argumentsUsage[] = $chunk;
        }

        foreach ($this->command->options as $option) {
            if (null === $option->shortcut) {
                $chunk = sprintf('--%s', $option->name);
            } else {
                $chunk = sprintf('-%s|--%s', $option->shortcut, $option->name);
            }
            if (CommandValueEnum::REQUIRED === $option->value) {
                $chunk = sprintf('%s=%s', $chunk, $option->type->name);
            } elseif (CommandValueEnum::OPTIONAL === $option->value) {
                $chunk = sprintf('%s[=%s]', $chunk, $option->type->name);
            }
            if ($option->isArray) {
                $chunk = sprintf('%s...', $chunk);
            }
            if (!$option->isRequired) {
                $chunk = sprintf('[%s]', $chunk);
            }

            $optionsUsage[] = $chunk;
        }

        if ($withHelp) {
            $optionsUsage[] = '[-h|--help]';
        }

        return implode(' ', [$this->alias, ...$optionsUsage, ...$argumentsUsage]);
    }
}
