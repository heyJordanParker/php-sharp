<?php declare(strict_types=1); namespace Demo;

use Lib\Standing;

class Roster
{
    final public static function report(\Sharp\Map|array $members): array { $members = \is_array($members) ? new \Sharp\Map(\array_merge([], ...\array_map(fn ($key, $value) => [Standing::from((string) $key), $value], \array_keys($members), $members))) : $members;

        $ranked = ((object) $members)->sortedBy(fn ($n) => -$n);
        $tens = ((object) $members)->map(fn ($n) => $n * 10);
        $busy = ((object) $members)->filter(fn ($n) => $n > 1);
        $kept = ((object) $members)->filter(function ($n) use (&$members) { return $n >= (((object) $members)->get(Standing::Active) ?? 0); });
        ((object) $members)->remove(Standing::Closed);
        return [
            \implode(",", $ranked),
            \implode(",", $tens),
            \implode(",", ((object) $kept)->map(fn ($n) => $n)),
            (string) (((object) $busy)->get(Standing::Active) ?? 0),
            (string) (((object) $busy)->get(Standing::Closed) ?? 0),
            (string) (((object) $members)->get(Standing::Active) ?? 0),
            (string) (((object) $members)->get(Standing::Closed) ?? 0),
        ];
    }
}
