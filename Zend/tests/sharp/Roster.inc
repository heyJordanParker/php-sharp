<?php declare(strict_types=1); namespace Demo;

use Lib\Standing;

class Roster
{
    public static function report(array $members): array
    {
        $ranked = ((object) $members)->sortedBy(fn ($n) => -$n);
        $tens = ((object) $members)->map(fn ($n) => $n * 10);
        $busy = ((object) $members)->filterValues(fn ($n) => $n > 1);
        $kept = ((object) $members)->filter(function ($n) use (&$members) { return $n >= (((object) $members)->get(Standing::Active) ?? 0); });
        ((object) $members)->delete(Standing::Closed);
        return [
            \implode(",", $ranked),
            \implode(",", $tens),
            \implode(",", $kept),
            (string) (((object) $busy)->get(Standing::Active) ?? 0),
            (string) (((object) $busy)->get(Standing::Closed) ?? 0),
            (string) (((object) $members)->get(Standing::Active) ?? 0),
            (string) (((object) $members)->get(Standing::Closed) ?? 0),
        ];
    }
}
