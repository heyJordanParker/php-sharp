<?php declare(strict_types=1); namespace Demo;

// Plain PHP returns a PHP# reader takes as lists: an int-keyed shape, a list shape, and int-keyed arrays as a shape's
// fields, one with the gaps array_filter leaves. A value that may be a list or a map keeps its string keys.
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

    /** @return array<int, int> */
    public static function gapped(): array
    {
        return [1 => 5, 2 => 6];
    }

    /** @return array{1: int, 2: int} */
    public static function fromOne(): array
    {
        return [1 => 7, 2 => 8];
    }

    /** @return list<int>|array<string, int> */
    public static function either(): array
    {
        return ['x' => 1, 'y' => 2];
    }

    /** @return array<string, list<int>|array<string, int>> */
    public static function eithers(): array
    {
        return ['a' => [1, 2], 'b' => ['x' => 1, 'y' => 2]];
    }

    public static function json(mixed $value): string
    {
        return json_encode($value instanceof \Traversable ? iterator_to_array($value) : $value);
    }
}
