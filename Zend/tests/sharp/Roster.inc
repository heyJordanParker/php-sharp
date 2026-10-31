<?php declare(strict_types=1); namespace Demo;

use Lib\Probe;
use Lib\Standing;

class Roster
{
    public static function report(\Sharp\Map|array $members): array { $members = \is_array($members) ? new \Sharp\Map(\array_merge([], ...\array_map(fn ($key, $value) => [Standing::from((string) $key), $value], \array_keys($members), $members))) : $members;

        $ranked = \Sharp\Internal\sortedBy($members, fn ($n) => -$n, null);
        $tens = \Sharp\Internal\map($members, fn ($n) => $n * 10);
        $busy = \Sharp\Internal\filterEntries($members, fn ($n) => $n > 1);
        $kept = \Sharp\Internal\filterEntries($members, function ($n) use (&$members) { return $n >= (\Sharp\Internal\get($members, Standing::Active) ?? 0); });
        \Sharp\Internal\removeKey($members, Standing::Closed);
        return [
            Probe::join(",", $ranked),
            Probe::join(",", $tens),
            Probe::join(",", \Sharp\Internal\map($kept, fn ($n) => $n)),
            (string) (\Sharp\Internal\get($busy, Standing::Active) ?? 0),
            (string) (\Sharp\Internal\get($busy, Standing::Closed) ?? 0),
            (string) (\Sharp\Internal\get($members, Standing::Active) ?? 0),
            (string) (\Sharp\Internal\get($members, Standing::Closed) ?? 0),
        ];
    }
}
