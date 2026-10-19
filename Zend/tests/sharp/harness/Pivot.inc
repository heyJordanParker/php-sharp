<?php declare(strict_types=1); namespace Demo;

// Plain PHP returns a PHP# reader takes as lists: an int-keyed shape, a list shape, and int-keyed arrays as a shape's
// fields, one with the gaps array_filter leaves.
final class Pivot
{
    /** @return array{0: int, 1: int} */
    public static function pair(): array
    {
        return [5, 6];
    }

    /** @return list{int, int} */
    public static function listed(): array
    {
        return [7, 8];
    }

    /** @return array{attached: array<int, int>, kept: array<int, int>} */
    public static function changes(): array
    {
        return ['attached' => [5], 'kept' => array_filter([0, 7, 8])];
    }
}
