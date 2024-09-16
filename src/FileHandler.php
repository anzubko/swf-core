<?php declare(strict_types=1);

namespace SWF;

use App\Config\SystemConfig;

final class FileHandler
{
    /**
     * Gets file contents as string.
     */
    public static function get(string $file): ?string
    {
        $contents = file_get_contents($file);

        return false === $contents ? null : $contents;
    }

    /**
     * Puts contents to file.
     */
    public static function put(string $file, mixed $contents, int $flags = 0, bool $createDir = true): bool
    {
        if ($createDir && !DirHandler::create(dirname($file)) || false === file_put_contents($file, $contents, $flags)) {
            return false;
        }

        @chmod($file, i(SystemConfig::class)->fileMode);

        return true;
    }

    /**
     * Puts variable to some PHP file.
     */
    public static function putVar(string $file, mixed $variable, int $flags = 0, bool $createDir = true): bool
    {
        if (!self::put($file, sprintf("<?php\n\nreturn %s;\n", var_export($variable, true)), $flags, $createDir)) {
            return false;
        }

        if (extension_loaded('zend-opcache')) {
            opcache_invalidate($file, true);
        }

        return true;
    }

    /**
     * Removes file.
     */
    public static function remove(string $file): bool
    {
        if (is_file($file)) {
            return unlink($file);
        }

        return true;
    }

    /**
     * Copies file.
     */
    public static function copy(string $source, string $target, bool $createDir = true): bool
    {
        if ($createDir && !DirHandler::create(dirname($target)) || !copy($source, $target)) {
            return false;
        }

        @chmod($target, i(SystemConfig::class)->fileMode);

        return true;
    }

    /**
     * Moves file.
     */
    public static function move(string $source, string $target, bool $createDir = true): bool
    {
        if ($createDir && !DirHandler::create(dirname($target)) || !rename($source, $target)) {
            return false;
        }

        return true;
    }

    /**
     * Gets some file statistics.
     */
    public static function stats(string $file): ?FileStats
    {
        $fileStats = @stat($file);
        if (false === $fileStats) {
            return null;
        }

        $imageSize = @getimagesize($file);
        if (false === $imageSize) {
            $imageSize = [];
        }

        return new FileStats(
            name: $file,
            dirname: dirname($file),
            basename: basename($file),
            extension: preg_match('/\.([^.]+)$/', $file, $M) ? $M[1] : null,
            size: $fileStats['size'],
            created: $fileStats['ctime'],
            modified: $fileStats['mtime'],
            width: $imageSize[0] ?? 0,
            height: $imageSize[1] ?? 0,
            type: $imageSize['mime'] ?? null,
        );
    }
}
