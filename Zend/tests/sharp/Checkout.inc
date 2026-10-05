<?php declare(strict_types=1); namespace Demo;

class Checkout
{
    public static function host(string $host): ?string
    {
        return $host != "" ? $host : null;
    }

    public static function sign(int $value): string
    {
        return $value > 0 ? "positive" : ($value < 0 ? "negative" : "zero");
    }

    public static function size(int $count, bool $wholesale): string
    {
        return ($wholesale ? $count > 100 : $count > 10) ? "large" : "small";
    }

    public static function cents(float $price): int
    {
        return (int)($price * 100);
    }

    public static function whole(float $ratio): int
    {
        return (int)$ratio;
    }

    public static function share(int $part, int $total): float
    {
        return (float)$part / $total;
    }

    public static function quantity(int $count): string
    {
        return (string)$count;
    }
}
