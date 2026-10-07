<?php

/** @generate-class-entries */

namespace Sharp;

/** @strict-properties */
final class Int
{
    public static function parse(mixed $value): int {}

    public static function tryParse(mixed $value): ?int {}
}

/** @strict-properties */
final class Float
{
    public static function parse(mixed $value): float {}

    public static function tryParse(mixed $value): ?float {}
}

/**
 * @strict-properties
 * @not-serializable
 */
final class Collection
{
    private function __construct() {}

    public function add(mixed $value): void {}

    public function set(int $index, mixed $value): void {}

    public function get(int|string|\BackedEnum $key): mixed {}

    public function entries(): array {}

    public function delete(int|string|\BackedEnum $key): void {}
}
