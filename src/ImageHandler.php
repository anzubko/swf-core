<?php declare(strict_types=1);

namespace SWF;

use GdImage;

class ImageHandler
{
    /**
     * Reading image from string.
     */
    public static function fromString(string|false|null $string): GdImage|false
    {
        if (empty($string)) {
            return false;
        }

        $image = imagecreatefromstring($string);
        if (false === $image) {
            return false;
        }

        return $image;
    }

    /**
     * Reading image from file.
     */
    public static function fromFile(string $file): GdImage|false
    {
        return static::fromString(FileHandler::get($file));
    }

    /**
     * Saving as PNG to file or returning as string.
     */
    public static function savePng(GdImage $image, ?string $file = null, int $quality = 0): string|bool
    {
        imagesavealpha($image, true);

        if (null !== $file) {
            $success = imagepng($image, $file, $quality);
            if ($success) {
                @chmod($file, ConfigHolder::get()->fileMode);
            }

            return $success;
        }

        ob_start(fn() => null);
        imagepng($image, null, $quality);

        return ob_get_clean();
    }

    /**
     * Saving as JPEG to file or returning as string.
     */
    public static function saveJpeg(GdImage $image, ?string $file = null, int $quality = 80): string|bool
    {
        $w = (int) imagesx($image);
        $h = (int) imagesy($image);

        /** @var GdImage $fixed */
        $fixed = imagecreatetruecolor($w, $h);
        imagefill($fixed, 0, 0, (int) imagecolorallocate($fixed, 255, 255, 255));
        imagecopy($fixed, $image, 0, 0, 0, 0, $w, $h);
        imageinterlace($fixed, true);

        if (null !== $file) {
            $success = imagejpeg($fixed, $file, $quality);
            if ($success) {
                @chmod($file, ConfigHolder::get()->fileMode);
            }

            return $success;
        }

        ob_start(fn() => null);
        imagejpeg($fixed, null, $quality);

        return ob_get_clean();
    }

    /**
     * Resizing image.
     */
    public static function resize(GdImage $image, int $nW, int $nH, bool $crop = false, bool $fit = false): GdImage
    {
        $oW = (int) imagesx($image);
        $oH = (int) imagesy($image);

        if ($oW === $nW && $oH === $nH || !$fit && $oW <= $nW && $oH <= $nH) {
            return $image;
        }

        if ($crop) {
            $resized = static::resizeWithCrop($image, $nW, $nH, $oW, $oH);
        } else {
            $resized = static::resizeNoCrop($image, $nW, $nH, $oW, $oH);
        }

        return $resized;
    }

    /**
     * Resizing image with cropping.
     */
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

    /**
     * Resizing image with no cropping.
     */
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
