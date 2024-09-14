<?php declare(strict_types=1);

namespace SWF\Command;

use SWF\Attribute\AsCommand;
use SWF\CommandProvider;
use SWF\ControllerProvider;
use SWF\ListenerProvider;

class SystemCommand
{
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
