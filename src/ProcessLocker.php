<?php declare(strict_types=1);

namespace SWF;

use LogicException;
use RuntimeException;

final class ProcessLocker
{
    private const DIR = APP_DIR . '/var/locks';

    private static self $instance;

    /**
     * @var string[]
     */
    private array $files = [];

    private function __construct()
    {
        if (!DirHandler::create(self::DIR)) {
            throw new RuntimeException(sprintf('Unable to create directory %s', self::DIR));
        }
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Acquires lock.
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    public function acquire(string $key): bool
    {
        if (isset($this->files[$key])) {
            throw new LogicException(sprintf('You already have lock with key: %s', $key));
        }

        $file = sprintf('%s/%s', self::DIR, $key);

        $handle = fopen($file, 'cb+');
        if (false === $handle) {
            throw new RuntimeException(sprintf('Unable to open file %s', $file));
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            return false;
        }

        $this->files[$key] = $file;

        return true;
    }

    /**
     * Releases lock.
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    public function release(string $key): void
    {
        if (!isset($this->files[$key])) {
            throw new LogicException(sprintf("Lock with key '%s' is not exists", $key));
        }

        if (!unlink($this->files[$key])) {
            throw new RuntimeException(sprintf('Unable to delete file %s', $this->files[$key]));
        }

        unset($this->files[$key]);
    }
}
