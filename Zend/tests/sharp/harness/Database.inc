<?php declare(strict_types=1);

namespace Lib\Database;

/** A plain PHP database facade whose array parameters are optional, as Laravel's `DB::select` is. */
final class Db
{
    /** The bindings the query ran with, which a call that leaves them out gives as an empty array. */
    public static function select(string $query, array $bindings = []): array
    {
        return $bindings;
    }

    /** How many ids the call passed. Plain PHP's own default holds two, so a call that passes the line's `[]` counts 0. */
    public static function recent(array $ids = [1, 2]): int
    {
        return count($ids);
    }
}
