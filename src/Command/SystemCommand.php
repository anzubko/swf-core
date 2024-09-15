<?php declare(strict_types=1);

namespace SWF\Command;

use App\Config\SystemConfig;
use SWF\Attribute\AsCommand;
use SWF\CommandProvider;
use SWF\ControllerProvider;
use SWF\DirHandler;
use SWF\ListenerProvider;

class SystemCommand
{
    #[AsCommand('system:cache:clear', 'Clears cache')]
    public function clearCache(): void
    {
        DirHandler::remove(i(SystemConfig::class)->cacheDir);

        echo "Done!\n";
    }

    #[AsCommand('system:commands', 'List all commands')]
    public function listAllCommands(): void
    {
        i(CommandProvider::class)->showAll();
    }

    #[AsCommand('system:controllers', 'List all controllers')]
    public function listAllControllers(): void
    {
        i(ControllerProvider::class)->showAll();
    }

    #[AsCommand('system:listeners', 'List all listeners')]
    public function listAllListeners(): void
    {
        i(ListenerProvider::class)->showAll();
    }
}
