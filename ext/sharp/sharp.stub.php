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

/** @strict-properties */
final class Position
{
    public readonly string $file;

    public readonly string $directory;

    public readonly int $line;

    public readonly int $column;

    public readonly string $function;

    public function __construct(string $file, int $line, int $column, string $function) {}
}

/**
 * @strict-properties
 * @not-serializable
 */
final class Environment
{
    /** @virtual */
    public array $arguments;

    /** @virtual */
    public string $currentDirectory;

    public function __construct() {}

    public function variable(string $name): ?string {}
}
