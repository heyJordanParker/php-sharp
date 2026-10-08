<?php declare(strict_types=1);

namespace Demo;

class Settings
{
    /** @var array<string, mixed> */
    private static array $values = ['plan' => 'pro', 'seats' => 5, 'tags' => ['a', 'b'], 'trial' => null];

    public static function get(string $key): mixed
    {
        return self::$values[$key] ?? null;
    }

    public static function raw(string $key)
    {
        return self::$values[$key] ?? null;
    }

    public static function describe(mixed $value): string
    {
        return \get_debug_type($value) . ' ' . \json_encode($value, \JSON_THROW_ON_ERROR);
    }
}
