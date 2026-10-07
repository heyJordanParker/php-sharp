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

    public function get(int|string $key): mixed {}

    public function entries(): array {}

    public function delete(int|string $key): void {}

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

/**
 * @strict-properties
 * @not-serializable
 */
final class Sequence implements \IteratorAggregate
{
    private function __construct() {}

    public static function from(iterable $source): Sequence {}

    public function filter(\Closure $predicate): Sequence {}

    public function map(\Closure $transform): Sequence {}

    public function take(int $count): Sequence {}

    public function toList(): array {}

    public function getIterator(): \Iterator {}
}
