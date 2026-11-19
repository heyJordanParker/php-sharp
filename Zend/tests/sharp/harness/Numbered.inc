<?php declare(strict_types=1);

namespace Lib;

// A plain PHP class whose array is keyed by numbers that are no positions.
final class Numbered
{
    /** @return array<int, string> */
    public static function names(): array
    {
        return [5 => 'a', 9 => 'b'];
    }
}
