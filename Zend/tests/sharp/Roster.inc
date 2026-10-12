<?php declare(strict_types=1); namespace Demo;

use Lib\Standing;

class Roster
{
    public static function report(array $members): array
    {
        $ranked = ((object) $members)->sortedBy(fn ($n) => -$n);
        $tens = ((object) $members)->map(fn ($n) => $n * 10);
        $busy = ((object) $members)->filterValues(fn ($n) => $n > 1);
        $kept = ((object) $members)->filterValues(function ($n) use (&$members) { return $n >= (((object) $members)->get(Standing::Active->value) ?? 0); });
        \array_key_exists(array: $members, key: Standing::Closed->value) && ((object) $members)->delete(Standing::Closed->value) === null;
        return [
            \implode(",", $ranked),
            \implode(",", $tens),
            \implode(",", $kept),
            (string) (((object) $busy)->get(Standing::Active->value) ?? 0),
            (string) (((object) $busy)->get(Standing::Closed->value) ?? 0),
            (string) (((object) $members)->get(Standing::Active->value) ?? 0),
            (string) (((object) $members)->get(Standing::Closed->value) ?? 0),
        ];
    }
}
