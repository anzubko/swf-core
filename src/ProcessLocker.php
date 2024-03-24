<?php declare(strict_types=1);

namespace SWF;

use LogicException;
use RuntimeException;

final class ProcessLocker
{
    /**
     * @var array<string,resource>
     */
    private array $handles = [];

    private static self $instance;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Locks file by key and returns true or returns false if file already in use.
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    public function lock(string $key): bool
    {
        if (isset($this->handles[$key])) {
            throw new LogicException(
                sprintf("Lock with key '%s' is already in use", $key),
            );
        }

        $file = str_replace('{KEY}', $key, ConfigHolder::get()->lockFile);
        if (!DirHandler::create(dirname($file))) {
            throw new RuntimeException(
                sprintf('Unable to create directory %s', dirname($file)),
            );
        }

        $handle = fopen($file, 'cb+');
        if (false === $handle) {
            throw new RuntimeException(
                sprintf('Unable to open file %s', $file),
            );
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            return false;
        }

        $this->handles[$key] = $handle;

        return true;
    }

    /**
     * Unlocks file by key.
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    public function unlock(string $key): void
    {
        if (!isset($this->handles[$key])) {
            throw new LogicException(
                sprintf("Lock with key '%s' is not exists", $key),
            );
        }

        $handle = $this->handles[$key];

        unset($this->handles[$key]);

        $file = stream_get_meta_data($handle)['uri'];
        if (!unlink($file)) {
            throw new RuntimeException(
                sprintf('Unable to close and remove file %s', $file),
            );
        }
    }
}
