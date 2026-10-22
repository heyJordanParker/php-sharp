<?php

namespace Lib;

/** A plain PHP library that takes callbacks with no docblock, as most do, and reads their parameters. */
final class Callbacks
{
    public static ?\Closure $handler = null;

    public static function apply(callable $step, int $value): int
    {
        return $step($value);
    }

    public static function applyClosure(\Closure $step, int $value): int
    {
        return $step($value);
    }

    public static function call(callable $step, int $value): void
    {
        $step($value);
    }

    public static function runHandler(int $value): int
    {
        return (self::$handler)($value);
    }

    /** Each parameter of `$callback` as a container that resolves callbacks by reflection reads it. */
    public static function describe(\Closure $callback): string
    {
        $parameters = (new \ReflectionFunction($callback))->getParameters();

        return implode(', ', array_map(
            fn (\ReflectionParameter $parameter): string => trim($parameter->getType() . ' $' . $parameter->getName()),
            $parameters,
        ));
    }
}

/** A plain PHP function of the library, which PHP# code reads as a value through its namespace. */
function magnitude(int $value): int
{
    return abs($value);
}
