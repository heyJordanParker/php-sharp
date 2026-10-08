<?php

/** @generate-class-entries */

namespace Sharp;

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

    public function filter(\Closure $predicate): array {}

    public function filterValues(\Closure $predicate): array {}

    public function map(\Closure $transform): array {}

    public function sumOf(\Closure $selector): int|float {}

    public function first(\Closure $predicate): mixed {}

    public function any(\Closure $predicate): bool {}

    public function groupBy(\Closure $key): array {}

    public function associateBy(\Closure $key): array {}

    public function sortedBy(\Closure $selector): array {}
}
