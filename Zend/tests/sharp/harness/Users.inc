<?php declare(strict_types=1);

namespace Lib;

/**
 * A plain PHP source of ids: `ids()` declares an `int`-keyed array and returns one whose keys have gaps, as
 * `array_filter` gives, and `listed()` declares a list.
 */
final class Users
{
    /** @var array<int, int> */
    public array $cached = [5 => 10, 9 => 20];

    /** @return array<int, int> */
    public function ids(): array
    {
        return array_filter([3 => 0, 5 => 10, 9 => 20]);
    }

    /** @return list<int> */
    public function listed(): array
    {
        return [1, 2];
    }
}
