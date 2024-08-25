<?php declare(strict_types=1);

namespace SWF;

use LogicException;
use RuntimeException;
use function count;
use function strlen;

final class CommandProvider
{
    private static ?ActionCache $cache;

    private static self $instance;

    /**
     * @throws LogicException
     * @throws RuntimeException
     */
    private function __construct()
    {
        self::$cache = ActionManager::getInstance()->getCache(CommandProcessor::class);
    }

    /**
     * @throws LogicException
     * @throws RuntimeException
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Gets current action.
     *
     * @return array{string, string|null}|null
     */
    public function getCurrentAction(): ?array
    {
        if (!isset(self::$cache, $_SERVER['argv'][1], self::$cache->data['commands'][$_SERVER['argv'][1]])) {
            return null;
        }

        $commandManager = new CommandManager($_SERVER['argv'][1], self::$cache->data['commands'][$_SERVER['argv'][1]]);

        for ($i = 0, $chunks = array_slice($_SERVER['argv'], 2); $i < count($chunks); $i++) {
            if (strlen($chunks[$i]) > 2 && '-' === $chunks[$i][0] && '-' === $chunks[$i][1]) {
                $commandManager->processOption($chunks[$i]);
            } elseif (
                strlen($chunks[$i]) > 1 && '-' === $chunks[$i][0] && '-' !== $chunks[$i][1]
            ) {
                $i += $commandManager->processShortOption($chunks[$i], $chunks[$i + 1] ?? null);
            } else {
                $commandManager->processArgument($chunks[$i]);
            }
        }

        $commandManager->checkForRequiredParams();

        return [$commandManager->getName(), null];
    }
}
