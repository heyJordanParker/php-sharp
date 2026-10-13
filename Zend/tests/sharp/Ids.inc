<?php declare(strict_types=1); namespace Demo;

class Ids
{
    public static function identity(object $value): int { return \spl_object_id($value); }

    public static function either(?object $first, object $fallback): ?object { return $first ?? $fallback; }

    public static function describe(object $source): string
    {
        if (($ticket = $source) instanceof \Demo\Ticket) {
            return ((object) $ticket)->code();
        }
        return "other";
    }
}

class Ticket
{
    public function code(): string { return "T7"; }
}
