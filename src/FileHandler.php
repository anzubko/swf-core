<?php declare(strict_types=1);

namespace SWF;

final class FileHandler
{
    /**
     * Getting file contents into string.
     */
    public static function get(string $file): string|false
    {
        return file_get_contents($file);
    }

    /**
     * Putting contents to file.
     */
    public static function put(string $file, mixed $contents, int $flags = 0, bool $createDir = true): bool
    {
        if ($createDir && !DirHandler::create(dirname($file)) || false === file_put_contents($file, $contents, $flags)) {
            return false;
        }

        @chmod($file, ConfigHolder::get()->fileMode);

        return true;
    }

    /**
     * Putting variable to some PHP file.
     */
    public static function putVar(string $file, mixed $variable, int $flags = 0, bool $createDir = true): bool
    {
        $contents = sprintf("<?php\n\nreturn %s;\n", var_export($variable, true));

        $success = static::put($file, $contents, $flags, $createDir);
        if ($success && extension_loaded('zend-opcache')) {
            opcache_invalidate($file, true);
        }

        return $success;
    }

    /**
     * File removing.
     */
    public static function remove(string $file): bool
    {
        if (is_file($file)) {
            return unlink($file);
        }

        return true;
    }

    /**
     * File coping.
     */
    public static function copy(string $source, string $target, bool $createDir = true): bool
    {
        if ($createDir && !DirHandler::create(dirname($target)) || !copy($source, $target)) {
            return false;
        }

        @chmod($target, ConfigHolder::get()->fileMode);

        return true;
    }

    /**
     * File moving.
     */
    public static function move(string $source, string $target, bool $createDir = true): bool
    {
        if ($createDir && !DirHandler::create(dirname($target)) || !rename($source, $target)) {
            return false;
        }

        return true;
    }

    /**
     * Getting some file statistics.
     *
     * @return array{name:string, size:int, modified:int, created:int, w:int, h:int, mime:string|null}|null
     */
    public static function stats(string $file): ?array
    {
        $fileStats = @stat($file);
        if (false === $fileStats) {
            return null;
        }

        $imageSize = @getimagesize($file);
        if (false === $imageSize) {
            $imageSize = [];
        }

        return [
            'name' => basename($file),
            'size' => $fileStats['size'],
            'modified' => $fileStats['mtime'],
            'created' => $fileStats['ctime'],
            'w' => $imageSize[0] ?? 0,
            'h' => $imageSize[1] ?? 0,
            'mime' => $imageSize['mime'] ?? null,
        ];
    }
}
