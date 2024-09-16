<?php declare(strict_types=1);

namespace SWF\Command;

use SWF\Attribute\AsCommand;
use SWF\CommandProvider;
use SWF\ConfigStorage;
use SWF\ControllerProvider;
use SWF\DirHandler;
use SWF\ListenerProvider;

class SystemCommand
{
    #[AsCommand('system:cache:clear', 'Clears cache')]
    public function clearCache(): void
    {
        DirHandler::clear(ConfigStorage::$system->cacheDir);

        echo "Done!\n";
    }

    #[AsCommand('system:commands', 'List all commands')]
    public function listAllCommands(): void
    {
        i(CommandProvider::class)->listAll();
    }

    #[AsCommand('system:controllers', 'List all controllers')]
    public function listAllControllers(): void
    {
        i(ControllerProvider::class)->listAll();
    }

    #[AsCommand('system:listeners', 'List all listeners')]
    public function listAllListeners(): void
    {
        i(ListenerProvider::class)->listAll();
    }
}
