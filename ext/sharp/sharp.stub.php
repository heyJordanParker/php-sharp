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

    public function insert(int $index, mixed $value): void {}

    public function removeAt(int $index): void {}

    public function get(int|string $key): mixed {}

    public function entries(): array {}

    public function delete(int|string $key): void {}

    public function put(int|string $key, mixed $value): mixed {}

    public function clear(): void {}

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

    public function require(string $name): string {}
}

/**
 * @strict-properties
 * @not-serializable
 */
final class Container
{
    public function get(string $id): mixed {}

    public function has(string $id): bool {}

    public static function scope(\Closure $run): mixed {}
}

namespace Sharp\Internal;

function readModule(string $path): void {}

function testModule(array $classes, array $bindings): void {}

function startTest(string $class): void {}

function endTest(): void {}
