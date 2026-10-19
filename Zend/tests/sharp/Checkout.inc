<?php declare(strict_types=1); namespace Demo;

class Checkout
{
    final public static function host(string $host): ?string
    {
        return $host !== "" ? $host : null;
    }

    final public static function sign(int $value): string
    {
        return $value > 0 ? "positive" : ($value < 0 ? "negative" : "zero");
    }

    final public static function size(int $count, bool $wholesale): string
    {
        return ($wholesale ? $count > 100 : $count > 10) ? "large" : "small";
    }

    final public static function cents(float $price): int
    {
        return (int)($price * 100);
    }

    final public static function whole(float $ratio): int
    {
        return (int)$ratio;
    }

    final public static function share(int $part, int $total): float
    {
        return (float)$part / $total;
    }

    final public static function quantity(int $count): string
    {
        return (string)$count;
    }

    final public static function total(string $price, ?string $count): int
    {
        return (int)(\Sharp\Float::parse($price) * 100) * (($count !== null ? \Sharp\Int::tryParse($count) : null) ?? 1);
    }

    final public static function page(string $value): int
    {
        return \Sharp\Int::parse($value);
    }

    final public static function price(string $value): float
    {
        return \Sharp\Float::parse($value);
    }
}
