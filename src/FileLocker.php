<?php
declare(strict_types=1);

namespace SWF;

use LogicException;
use RuntimeException;
use function array_key_exists;

final class FileLocker
{
    /**
     * @var mixed[]
     */
    private array $files = [];

    /**
     * Acquires lock.
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    public function acquire(string $key, bool $wait = true): bool
    {
        if (isset($this->files[$key])) {
            throw new LogicException(sprintf('You already have lock with key %s', $key));
        }

        $file = sprintf('%s/%s', ConfigStorage::$system->locksDir, $key);

        $handle = @fopen($file, 'cb+');
        if ($handle === false) {
            if (!DirHandler::create(dirname($file))) {
                throw new RuntimeException(sprintf('Unable to create directory %s', dirname($file)));
            }

            $handle = fopen($file, 'cb+');
            if ($handle === false) {
                throw new RuntimeException(sprintf('Unable to open file %s', $file));
            }
        }

        if ($wait) {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException(sprintf('Unable to lock file %s', $file));
            }
        } else {
            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                return false;
            }
        }

        $this->files[$key] = ['file' => $file, 'handle' => $handle];

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
        if (!array_key_exists($key, $this->files)) {
            throw new LogicException(sprintf('Lock with key %s is not exists', $key));
        }

        if (!flock($this->files[$key]['handle'], LOCK_UN)) {
            throw new RuntimeException(sprintf('Unable to unlock file %s', $this->files[$key]['file']));
        }

        unset($this->files[$key]);
    }
}
