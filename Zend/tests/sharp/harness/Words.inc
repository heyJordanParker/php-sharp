<?php declare(strict_types=1);

namespace Lib;

/** A project's plain PHP class that reaches PHP's built-in string functions for PHP# code. */
final class Words
{
    public static function distance(string $from, string $to): int
    {
        return levenshtein($from, $to);
    }

    public static function capitalized(string $text): string
    {
        return ucfirst($text);
    }

    public static function repeated(string $text, int $times): string
    {
        return str_repeat($text, $times);
    }
}
