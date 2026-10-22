<?php declare(strict_types=1);

namespace Lib;

/** A project's plain PHP class that tells a test what PHP sees of the values PHP# code hands it. */
final class Probe
{
    public static function className(object $value): string
    {
        return get_class($value);
    }

    public static function named(string $type): string
    {
        return $type;
    }

    public static function typeName(mixed $value): string
    {
        return get_debug_type($value);
    }

    public static function id(object $value): int
    {
        return spl_object_id($value);
    }

    public static function join(string $separator, array $parts): string
    {
        return implode($separator, $parts);
    }

    /** @return array<string, mixed> */
    public static function fields(object $value): array
    {
        return get_object_vars($value);
    }

    public static function firstKey(array $values): int|string|null
    {
        return array_key_first($values);
    }

    public static function counted(iterable $values): int
    {
        return iterator_count($values);
    }
}
