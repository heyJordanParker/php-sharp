<?php declare(strict_types=1);

namespace Lib;

// A plain PHP parent whose method a PHP# class inherits, so `Class<T>.methods()` gives the method's PHP types as PHP#
// writes them.
abstract class Crate
{
    public function weigh(?int $grams, array $tags = []): ?float
    {
        return $grams === null ? null : $grams / 1000;
    }
}
