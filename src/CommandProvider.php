<?php declare(strict_types=1);

namespace SWF;

use LogicException;
use RuntimeException;

final class CommandProvider
{
    private static ActionCache $cache;

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
        if (!isset($_SERVER['argv'][1])) {
            return null;
        }

        $action = self::$cache->data['commands'][$_SERVER['argv'][1]] ?? null;
        if (null === $action) {
            return null;
        }

        return [$action, null];
    }
}
