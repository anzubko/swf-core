<?php
declare(strict_types=1);

namespace SWF;

use GdImage;
use function is_string;

final class ImageHandler
{
    /**
     * Reads image from string.
     */
    public static function fromString(string $string): ?GdImage
    {
        $image = imagecreatefromstring($string);
        if (false === $image) {
            return null;
        }

        return $image;
    }

    /**
     * Reads image from file.
     */
    public static function fromFile(string $file): ?GdImage
    {
        $contents = FileHandler::get($file);
        if (null === $contents) {
            return null;
        }

        return self::fromString($contents);
    }

    /**
     * Transforms image to PNG.
     */
    public static function toPng(GdImage $image, int $quality = 0): ?string
    {
        if (!ob_start(fn() => null)) {
            return null;
        }

        imagesavealpha($image, true);
        imagepng($image, null, $quality);

        $contents = ob_get_clean();
        if (false === $contents) {
            return null;
        }

        return $contents;
    }

    /**
     * Saves as PNG.
     *
     * @param resource|string $file
     */
    public static function savePng(GdImage $image, mixed $file, int $quality = 0): bool
    {
        imagesavealpha($image, true);

        $success = imagepng($image, $file, $quality);
        if ($success && is_string($file)) {
            @chmod($file, ConfigStorage::$system->fileMode);
        }

        return $success;
    }

    /**
     * Transforms image to JPEG.
     */
    public static function toJpg(GdImage $image, int $quality = 100): ?string
    {
        if (!ob_start(fn() => null)) {
            return null;
        }

        imagejpeg(self::fixJpeg($image), null, $quality);

        $contents = ob_get_clean();
        if (false === $contents) {
            return null;
        }

        return $contents;
    }

    /**
     * Saves as JPEG.
     *
     * @param resource|string $file
     */
    public static function saveJpeg(GdImage $image, mixed $file, int $quality = 100): bool
    {
        $success = imagejpeg(self::fixJpeg($image), $file, $quality);
        if ($success && is_string($file)) {
            @chmod($file, ConfigStorage::$system->fileMode);
        }

        return $success;
    }

    private static function fixJpeg(GdImage $image): GdImage
    {
        $w = (int) imagesx($image);
        $h = (int) imagesy($image);

        /** @var GdImage $fixed */
        $fixed = imagecreatetruecolor($w, $h);
        imagefill($fixed, 0, 0, (int) imagecolorallocate($fixed, 255, 255, 255));
        imagecopy($fixed, $image, 0, 0, 0, 0, $w, $h);
        imageinterlace($fixed, true);

        return $fixed;
    }

    /**
     * Resizes image.
     */
    public static function resize(GdImage $image, int $nW, int $nH, bool $crop = false, bool $fit = false): GdImage
    {
        $oW = (int) imagesx($image);
        $oH = (int) imagesy($image);

        if ($oW === $nW && $oH === $nH || !$fit && $oW <= $nW && $oH <= $nH) {
            return $image;
        }

        if ($crop) {
            $resized = self::resizeWithCrop($image, $nW, $nH, $oW, $oH);
        } else {
            $resized = self::resizeNoCrop($image, $nW, $nH, $oW, $oH);
        }

        return $resized;
    }

    protected static function resizeWithCrop(GdImage $image, int $nW, int $nH, int $oW, int $oH): GdImage
    {
        $ratio = $oW / $oH;
        $cW = $nW;
        $cH = $nH;

        if ($cW / $cH > $ratio) {
            $cH = (int) round($cW / $ratio);
        } else {
            $cW = (int) round($cH * $ratio);
        }

        $cX = (int) round(($nW - $cW) / 2);
        $cY = (int) round(($nH - $cH) / 2);

        /** @var GdImage $resized */
        $resized = imagecreatetruecolor($nW, $nH);
        imagefill($resized, 0, 0, (int) imagecolorallocatealpha($resized, 0, 0, 0, 127));
        imagecopyresampled($resized, $image, $cX, $cY, 0, 0, $cW, $cH, $oW, $oH);

        return $resized;
    }

    protected static function resizeNoCrop(GdImage $image, int $nW, int $nH, int $oW, int $oH): GdImage
    {
        $ratio = $oW / $oH;
        if ($nW / $nH > $ratio) {
            $nW = (int) round($nH * $ratio);
        } else {
            $nH = (int) round($nW / $ratio);
        }

        /** @var GdImage $resized */
        $resized = imagecreatetruecolor($nW, $nH);
        imagefill($resized, 0, 0, (int) imagecolorallocatealpha($resized, 0, 0, 0, 127));
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $nW, $nH, $oW, $oH);

        return $resized;
    }
}
