<?php declare(strict_types=1);

namespace Demo;

class Helper
{
    public static function take(int $value): int
    {
        return $value;
    }

    /**
     * Gives back any value, typed for the checker as an int, as a library with a wrong docblock does.
     *
     * @return int
     */
    public static function trusted(mixed $value): mixed
    {
        return $value;
    }
}
