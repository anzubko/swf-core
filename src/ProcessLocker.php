<?php declare(strict_types=1);

namespace SWF;

use LogicException;
use RuntimeException;

final class ProcessLocker
{
    /** @var string[] */
    private array $files = [];

    private static self $instance;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Obtains lock and returns true on success or false if another process has this lock.
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    public function lock(string $key): bool
    {
        if (isset($this->files[$key])) {
            throw new LogicException(sprintf('You already have lock with key: %s', $key));
        }

        $file = str_replace('{KEY}', $key, ConfigHolder::get()->lockFile);
        if (!DirHandler::create(dirname($file))) {
            throw new RuntimeException(sprintf('Unable to create directory %s', dirname($file)));
        }

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
    public function unlock(string $key): void
    {
        if (!isset($this->files[$key])) {
            throw new LogicException(sprintf("Lock with key '%s' is not exists", $key));
        }

        $file = $this->files[$key];

        unset($this->files[$key]);

        if (!unlink($file)) {
            throw new RuntimeException(sprintf('Unable to delete file %s', $file));
        }
    }
}
