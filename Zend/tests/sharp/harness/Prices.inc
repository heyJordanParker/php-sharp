<?php declare(strict_types=1);

namespace Demo;

class Prices
{
    /** @return array<int, int> */
    public static function all(): array
    {
        return [10 => 3, 20 => 4];
    }

    /** @return list<int> */
    public static function listed(): array
    {
        return [3, 4];
    }
}
