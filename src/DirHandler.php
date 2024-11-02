<?php
declare(strict_types=1);

namespace SWF;

use RuntimeException;
use Throwable;

final class DirHandler
{
    private static string $tempDir;

    /**
     * @var string[]
     */
    private static array $tempSubDirs = [];

    /**
     * Scans directory.
     *
     * @return string[]
     */
    public static function scan(string $dir, bool $recursive = false, bool $withDir = false, int $order = SCANDIR_SORT_ASCENDING): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $items = scandir($dir, $order);
        if ($items === false) {
            return [];
        }

        $scanned = [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $dirWItem = sprintf('%s/%s', $dir, $item);
            if ($recursive && is_dir($dirWItem)) {
                foreach (self::scan($dirWItem, true, false, $order) as $subItem) {
                    if ($withDir) {
                        $scanned[] = sprintf('%s/%s', $dirWItem, $subItem);
                    } else {
                        $scanned[] = sprintf('%s/%s', $item, $subItem);
                    }
                }
            } elseif ($withDir) {
                $scanned[] = $dirWItem;
            } else {
                $scanned[] = $item;
            }
        }

        return $scanned;
    }

    /**
     * Creates directory.
     */
    public static function create(string $dir): bool
    {
        if (is_dir($dir)) {
            return true;
        }

        $success = mkdir($dir, recursive: true);
        if ($success) {
            @chmod($dir, ConfigStorage::$system->dirMode);
        }

        return $success;
    }

    /**
     * Removes directory.
     */
    public static function remove(string $dir, bool $recursive = true): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $success = true;
        if ($recursive) {
            $items = scandir($dir);
            if ($items === false) {
                return false;
            }

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $dirWItem = sprintf('%s/%s', $dir, $item);
                if (is_dir($dirWItem)) {
                    if (!self::remove($dirWItem)) {
                        $success = false;
                    }
                } elseif (!unlink($dirWItem)) {
                    $success = false;
                }
            }
        }

        if ($success) {
            return rmdir($dir);
        }

        return false;
    }

    /**
     * Clears directory.
     */
    public static function clear(string $dir, bool $recursive = true): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $items = scandir($dir);
        if ($items === false) {
            return false;
        }

        $success = true;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $dirWItem = sprintf('%s/%s', $dir, $item);
            if (is_dir($dirWItem)) {
                if (!self::remove($dirWItem, $recursive)) {
                    $success = false;
                }
            } elseif (!unlink($dirWItem)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Copies directory.
     */
    public static function copy(string $source, string $target): bool
    {
        if (!self::create($target)) {
            return false;
        }

        $items = scandir($source);
        if ($items === false) {
            return false;
        }

        $success = true;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $sourceWItem = sprintf('%s/%s', $source, $item);
            $targetWItem = sprintf('%s/%s', $target, $item);

            if (is_dir($sourceWItem)) {
                if (!self::copy($sourceWItem, $targetWItem)) {
                    $success = false;
                }
            } elseif (!FileHandler::copy($sourceWItem, $targetWItem, false)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Moves directory.
     */
    public static function move(string $source, string $target): bool
    {
        return self::create(dirname($target)) && rename($source, $target);
    }

    /**
     * Makes temporary directory.
     *
     * @throws RuntimeException
     */
    public static function temporary(): string
    {
        if (!isset(self::$tempDir)) {
            $tempDir = realpath(sys_get_temp_dir());
            if ($tempDir === false) {
                throw new RuntimeException('Invalid system temporary directory');
            }

            register_shutdown_function(
                function () {
                    register_shutdown_function(
                        function () {
                            register_shutdown_function(
                                function () {
                                    foreach (self::$tempSubDirs as $dir) {
                                        try {
                                            self::remove($dir);
                                        } catch (Throwable $e) {
                                            i(CommonLogger::class)->error($e->getMessage());
                                        }
                                    }
                                }
                            );
                        }
                    );
                }
            );

            self::$tempDir = $tempDir;
        }

        for ($i = 1; $i <= 7; $i++) {
            $tempSubDir = sprintf('%s/%s', self::$tempDir, TextHandler::random());
            if (@mkdir($tempSubDir, 0600, true)) {
                return self::$tempSubDirs[] = $tempSubDir;
            }
        }

        throw new RuntimeException('Unable to create temporary subdirectory');
    }
}
