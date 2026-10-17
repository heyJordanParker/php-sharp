<?php declare(strict_types=1);

namespace Lib;

/** Hands what PHP# code passes it to the test, while that code runs. */
final class Witness
{
    /** @var (\Closure(object): void)|null */
    public static ?\Closure $look = null;

    public static function see(object $object): void
    {
        if (self::$look !== null) {
            (self::$look)($object);
        }
    }

    /** Prints the text, so a test's expected output shows where PHP# code ran, a static's hook included. */
    public static function say(string $text): void
    {
        echo $text;
    }
}
