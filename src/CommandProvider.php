<?php declare(strict_types=1);

namespace SWF;

use LogicException;
use RuntimeException;
use function count;
use function strlen;

final class CommandProvider
{
    private static ActionCache $cache;

    private static self $instance;

    /**
     * @throws LogicException
     * @throws RuntimeException
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * @throws LogicException
     * @throws RuntimeException
     */
    private function __construct()
    {
        self::$cache = ActionManager::getInstance()->getCache(CommandProcessor::class);
    }

    /**
     * Gets current action.
     *
     * @return array{string, string|null}|null
     */
    public function getCurrentAction(): ?array
    {
        if (!isset($_SERVER['argv'][1])) {
            $this->showAll();
            exit;
        }

        $name = $_SERVER['argv'][1];
        if (!isset(self::$cache->data['commands'][$name])) {
            return null;
        }

        $commandManager = new CommandManager($name, self::$cache->data['commands'][$name]);

        for ($i = 0, $chunks = array_slice($_SERVER['argv'], 2); $i < count($chunks); $i++) {
            $chunk = $chunks[$i];
            if (strlen($chunk) > 2 && '-' === $chunk[0] && '-' === $chunk[1]) {
                $commandManager->processOption($chunk);
            } elseif (
                strlen($chunk) > 1 && '-' === $chunk[0] && '-' !== $chunk[1]
            ) {
                $i += $commandManager->processShortOption($chunk, $chunks[$i + 1] ?? null);
            } else {
                $commandManager->processArgument($chunk);
            }
        }

        $commandManager->checkForRequiredParams();

        return [$commandManager->getAction(), null];
    }

    public function showAll(): void
    {
        $commands = self::$cache->data['commands'];
        if (count($commands) === 0) {
            echo "No commands found.\n";
            exit;
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
}
