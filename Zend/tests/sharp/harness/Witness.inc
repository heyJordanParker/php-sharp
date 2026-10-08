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
}
