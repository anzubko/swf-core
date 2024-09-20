<?php declare(strict_types=1);

namespace SWF\Command;

use SWF\Attribute\AsCommand;
use SWF\CommandLineManager;
use SWF\CommandProvider;
use SWF\ConfigStorage;
use SWF\ControllerProvider;
use SWF\DirHandler;
use SWF\ListenerProvider;
use Throwable;

class SystemCommand
{
    /**
     * @throws Throwable
     */
    #[AsCommand('system:cache:clear', 'Clears cache')]
    public function clearCache(): void
    {
        DirHandler::clear(ConfigStorage::$system->cacheDir);

        i(CommandLineManager::class)->writeLn('Done!');
    }

    /**
     * @throws Throwable
     */
    #[AsCommand('system:commands', 'List all commands')]
    public function listAllCommands(): void
    {
        i(CommandProvider::class)->listAll();
    }

    /**
     * @throws Throwable
     */
    #[AsCommand('system:controllers', 'List all controllers')]
    public function listAllControllers(): void
    {
        i(ControllerProvider::class)->listAll();
    }

    /**
     * @throws Throwable
     */
    #[AsCommand('system:listeners', 'List all listeners')]
    public function listAllListeners(): void
    {
        i(ListenerProvider::class)->listAll();
    }
}
