<?php declare(strict_types=1);

namespace Lib;

/**
 * A plain PHP class whose `int`-keyed array properties have gaps in their keys, as `array_filter` leaves them, which
 * PHP# reads and writes as a `List`.
 */
final class Roster
{
    /** @var array<int, string> */
    public array $names = [3 => 'ada', 7 => 'bo'];

    /** @var array<int, int> */
    public array $scores = [2 => 5, 6 => 9];

    /** @var array<int, string> */
    public static array $tags = [4 => 'x', 9 => 'y'];

    /** @var array<int, string>|null */
    public ?array $aliases = [5 => 'al'];

    private static ?Roster $shared = null;

    public static function shared(): Roster
    {
        return self::$shared ??= new Roster();
    }
}
