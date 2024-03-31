<?php declare(strict_types=1);

namespace SWF;

use function strlen;

final class TextHandler
{
    /**
     * To lower case.
     */
    public static function lc(?string $string): string
    {
        if (null === $string) {
            return '';
        }

        return mb_strtolower($string);
    }

    /**
     * First char to lower case.
     */
    public static function lcFirst(?string $string): string
    {
        if (null === $string) {
            return '';
        }

        return mb_strtolower(mb_substr($string, 0, 1)) . mb_substr($string, 1);
    }

    /**
     * To upper case.
     */
    public static function uc(?string $string): string
    {
        if (null === $string) {
            return '';
        }

        return mb_strtoupper($string);
    }

    /**
     * First char to upper case.
     */
    public static function ucFirst(?string $string): string
    {
        if (null === $string) {
            return '';
        }

        return mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1);
    }

    /**
     * Trim both sides.
     */
    public static function trim(?string $string): string
    {
        if (null === $string) {
            return '';
        }

        return trim($string, " \t\n\r\v\f\0");
    }

    /**
     * Trim right side.
     */
    public static function rTrim(?string $string): string
    {
        if (null === $string) {
            return '';
        }

        return rtrim($string, " \t\n\r\v\f\0");
    }

    /**
     * Trim left side.
     */
    public static function lTrim(?string $string): string
    {
        if (null === $string) {
            return '';
        }

        return ltrim($string, " \t\n\r\v\f\0");
    }

    /**
     * Trim both sides and convert all sequential spaces to one.
     */
    public static function fTrim(?string $string, int $limit = 0): string
    {
        if (null === $string) {
            return '';
        }

        $string = trim((string) preg_replace("/[ \t\n\r\v\f\0]+/", ' ', $string));
        if ($limit <= 0) {
            return $string;
        }

        return rtrim(mb_substr($string, 0, $limit));
    }

    /**
     * Trim both sides and convert all sequential spaces to one, but leave new lines.
     */
    public static function mTrim(?string $string, int $limit = 0): string
    {
        if (null === $string) {
            return '';
        }

        $string = trim((string) preg_replace(['/\h+/', "/[ \t\n\r\v\f\0]*\\v[ \t\n\r\v\f\0]*/"], [' ', "\n"], $string));
        if ($limit <= 0) {
            return $string;
        }

        return rtrim(mb_substr($string, 0, $limit));
    }

    /**
     * Cuts string.
     */
    public static function cut(?string $string, int $min, ?int $max = null): string
    {
        if (null === $string) {
            return '';
        }

        $string = trim((string) preg_replace("/[ \t\n\r\v\f\0]+/", ' ', $string));
        if (mb_strlen($string) <= $min) {
            return $string;
        }

        if (null !== $max) {
            if (preg_match(sprintf('/^(.{%d,%d}?)[^\p{L}\d]/u', $min, $max - 1), $string, $M)) {
                $string = $M[1];
            } else {
                $string = rtrim(mb_substr($string, 0, $max - 1));
            }
        } else {
            $string = rtrim(mb_substr($string, 0, $min - 1));
        }

        return $string . '...';
    }

    /**
     * Generates random string.
     */
    public static function random(int $size = 32, string $chars = '[alpha][digit]'): string
    {
        $chars = str_replace(
            [
                '[alpha]',
                '[upper]',
                '[lower]',
                '[digit]',
            ],
            [
                'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz',
                'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
                'abcdefghijklmnopqrstuvwxyz',
                '0123456789',
            ],
            $chars,
        );

        $string = str_repeat(' ', $size);
        for ($i = 0, $max = strlen($chars) - 1; $i < $size; $i++) {
            $string[$i] = $chars[mt_rand(0, $max)];
        }

        return $string;
    }
}
