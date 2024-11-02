<?php
declare(strict_types=1);

namespace SWF;

use function strlen;

final class TextHandler
{
    /**
     * To lower case.
     */
    public static function lc(?string $string): string
    {
        if ($string === null) {
            return '';
        }

        return mb_strtolower($string);
    }

    /**
     * First char to lower case.
     */
    public static function lcFirst(?string $string): string
    {
        if ($string === null) {
            return '';
        }

        return mb_strtolower(mb_substr($string, 0, 1)) . mb_substr($string, 1);
    }

    /**
     * To upper case.
     */
    public static function uc(?string $string): string
    {
        if ($string === null) {
            return '';
        }

        return mb_strtoupper($string);
    }

    /**
     * First char to upper case.
     */
    public static function ucFirst(?string $string): string
    {
        if ($string === null) {
            return '';
        }

        return mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1);
    }

    /**
     * Trims both sides.
     */
    public static function trim(?string $string): string
    {
        if ($string === null) {
            return '';
        }

        return trim($string, " \t\n\r\v\f");
    }

    /**
     * Trims right side.
     */
    public static function rTrim(?string $string): string
    {
        if ($string === null) {
            return '';
        }

        return rtrim($string, " \t\n\r\v\f");
    }

    /**
     * Trims left side.
     */
    public static function lTrim(?string $string): string
    {
        if ($string === null) {
            return '';
        }

        return ltrim($string, " \t\n\r\v\f");
    }

    /**
     * Trims both sides and converts all sequential spaces to one.
     */
    public static function fTrim(?string $string, int $limit = 0): string
    {
        if ($string === null) {
            return '';
        }

        $string = trim((string) preg_replace("/[ \t\n\r\v\f]+/", ' ', $string));
        if ($limit <= 0) {
            return $string;
        }

        return rtrim(mb_substr($string, 0, $limit));
    }

    /**
     * Trims both sides and converts all sequential spaces to one, but leaves new lines.
     */
    public static function mTrim(?string $string, int $limit = 0): string
    {
        if ($string === null) {
            return '';
        }

        $string = trim((string) preg_replace(['/\h+/', "/[ \t\n\r\v\f]*\\v[ \t\n\r\v\f]*/"], [' ', "\n"], $string));
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
        if ($string === null) {
            return '';
        }

        $string = trim((string) preg_replace("/[ \t\n\r\v\f]+/", ' ', $string));
        if (mb_strlen($string) <= $min) {
            return $string;
        }

        if ($max !== null) {
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
     * Returns true if string starts from one of the needle strings or false otherwise.
     *
     * @param string[] $needles
     */
    public static function startsWith(string $string, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_starts_with($string, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns true if string ends with one of the needle strings or false otherwise.
     *
     * @param string[] $needles
     */
    public static function endsWith(string $string, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_ends_with($string, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns true if string contains one of the needle strings or false otherwise.
     *
     * @param string[] $needles
     */
    public static function contains(string $string, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($string, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generates random string.
     */
    public static function random(int $size = 32, string $chars = '[alpha][digit]'): string
    {
        $chars = strtr($chars, [
            '[alpha]' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz',
            '[upper]' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            '[lower]' => 'abcdefghijklmnopqrstuvwxyz',
            '[digit]' => '0123456789',
        ]);

        $string = str_repeat(' ', $size);
        for ($i = 0, $max = strlen($chars) - 1; $i < $size; $i++) {
            $string[$i] = $chars[mt_rand(0, $max)];
        }

        return $string;
    }
}
