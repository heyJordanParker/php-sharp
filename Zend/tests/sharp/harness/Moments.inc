<?php declare(strict_types=1);

namespace Lib;

/** A project's plain PHP class that gives PHP# code PHP's own dates, as a Laravel model's Carbon attributes do. */
final class Moments
{
    public static function at(string $local, string $zone): \DateTimeImmutable
    {
        return new \DateTimeImmutable($local, new \DateTimeZone($zone));
    }

    public static function mutableAt(string $local, string $zone): \DateTime
    {
        return new \DateTime($local, new \DateTimeZone($zone));
    }
}
