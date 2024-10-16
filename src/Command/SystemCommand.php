<?php
declare(strict_types=1);

namespace SWF\Command;

use SWF\Attribute\AsCommand;
use SWF\CommandLineManager;
use SWF\CommandUtil;
use SWF\ConfigStorage;
use SWF\ConsumerUtil;
use SWF\ControllerUtil;
use SWF\DirHandler;
use SWF\ListenerUtil;
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

        CommandLineManager::writeLn('Done!');
    }

    /**
     * @throws Throwable
     */
    #[AsCommand('system:commands', 'List all commands')]
    public function listAllCommands(): void
    {
        CommandUtil::listAll();
    }

    /**
     * @throws Throwable
     */
    #[AsCommand('system:consumers', 'List all consumers')]
    public function listAllConsumers(): void
    {
        ConsumerUtil::listAll();
    }

    /**
     * @throws Throwable
     */
    #[AsCommand('system:controllers', 'List all controllers')]
    public function listAllControllers(): void
    {
        ControllerUtil::listAll();
    }

    /**
     * @throws Throwable
     */
    #[AsCommand('system:listeners', 'List all listeners')]
    public function listAllListeners(): void
    {
        ListenerUtil::listAll();
    }
}
