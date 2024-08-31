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

        return [$commandManager->getName(), null];
    }
}
