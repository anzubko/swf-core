<?php declare(strict_types=1);

namespace SWF;

use RuntimeException;

final class DirHandler
{
    private static string $tempDir;

    /**
     * @var string[]
     */
    private static array $tempSubDirs = [];

    /**
     * Directory scanning.
     *
     * @return string[]
     */
    public static function scan(string $dir, bool $recursive = false, bool $withDir = false, int $order = SCANDIR_SORT_ASCENDING): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $items = scandir($dir, $order);
        if (false === $items) {
            return [];
        }

        $scanned = [];
        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            if (!$recursive || is_file("$dir/$item")) {
                if ($withDir) {
                    $scanned[] = "$dir/$item";
                } else {
                    $scanned[] = $item;
                }
            } else {
                foreach (self::scan("$dir/$item", true, false, $order) as $subItem) {
                    if ($withDir) {
                        $scanned[] = "$dir/$item/$subItem";
                    } else {
                        $scanned[] = "$item/$subItem";
                    }
                }
            }
        }

        return $scanned;
    }

    /**
     * Directory creation.
     */
    public static function create(string $dir): bool
    {
        if (is_dir($dir)) {
            return true;
        }

        $success = mkdir($dir, recursive: true);
        if ($success) {
            @chmod($dir, ConfigProvider::get('system', 'dirMode'));
        }

        return $success;
    }

    /**
     * Directory removing.
     */
    public static function remove(string $dir, bool $recursive = true): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $success = true;
        if ($recursive) {
            $items = scandir($dir);
            if (false === $items) {
                return false;
            }

            foreach ($items as $item) {
                if ('.' === $item || '..' === $item) {
                    continue;
                }

                if (is_dir("$dir/$item")) {
                    if (!self::remove("$dir/$item")) {
                        $success = false;
                    }
                } elseif (!unlink("$dir/$item")) {
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
     * Directory clearing.
     */
    public static function clear(string $dir, bool $recursive = true): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $items = scandir($dir);
        if (false === $items) {
            return false;
        }

        $success = true;
        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            if (is_dir("$dir/$item")) {
                if (!self::remove("$dir/$item", $recursive)) {
                    $success = false;
                }
            } elseif (!unlink("$dir/$item")) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Directory coping.
     */
    public static function copy(string $source, string $target): bool
    {
        if (!self::create($target)) {
            return false;
        }

        $items = scandir($source);
        if (false === $items) {
            return false;
        }

        $success = true;
        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            if (is_dir("$source/$item")) {
                if (!self::copy("$source/$item", "$target/$item")) {
                    $success = false;
                }
            } elseif (!FileHandler::copy("$source/$item", "$target/$item", false)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Directory moving.
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
            if (false === $tempDir) {
                throw new RuntimeException('Invalid system temporary directory');
            }

            register_shutdown_function(
                function () {
                    register_shutdown_function(
                        function () {
                            register_shutdown_function(
                                function () {
                                    foreach (self::$tempSubDirs as $dir) {
                                        self::remove($dir);
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
