<?php declare(strict_types=1);

namespace SWF;

final class FileHandler
{
    /**
     * Getting file contents into string.
     */
    public static function get(string $file): ?string
    {
        $contents = file_get_contents($file);

        return false === $contents ? null : $contents;
    }

    /**
     * Putting contents to file.
     */
    public static function put(string $file, mixed $contents, int $flags = 0, bool $createDir = true): bool
    {
        if ($createDir && !DirHandler::create(dirname($file)) || false === file_put_contents($file, $contents, $flags)) {
            return false;
        }

        @chmod($file, config('system')->get('fileMode'));

        return true;
    }

    /**
     * Putting variable to some PHP file.
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

        @chmod($target, config('system')->get('fileMode'));

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
     * @return array{name:string, size:int, modified:int, created:int, w:int, h:int, type:string|null}|null
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
            'type' => $imageSize['mime'] ?? null,
        ];
    }
}
