<?php

namespace Lib;

// Plain PHP methods that take PHP arrays, by value, spread and nullable, which a PHP# `Map` keyed by a backed enum
// reaches with each case's backing value as its key.
final class Tally
{
    public static function describe(array $counts): string
    {
        return json_encode($counts);
    }

    public static function each(array ...$rolls): string
    {
        return implode(';', array_map('json_encode', $rolls));
    }

    public static function maybe(?array $counts): string
    {
        return $counts === null ? 'none' : json_encode($counts);
    }
}
