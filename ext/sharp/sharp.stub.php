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

    public function add(mixed $value): bool {}

    public function set(int $index, mixed $value): mixed {}

    public function insert(int $index, mixed $value): void {}

    public function removeAt(int $index): void {}

    public function get(mixed $key): mixed {}

    public function entries(?string $typeArguments = null): Map {}

    public function has(mixed $key): bool {}

    public function contains(mixed $value): bool {}

    public function remove(mixed $value): bool {}

    public function put(mixed $key, mixed $value): mixed {}

    public function clear(): void {}

    public function count(): int {}

    public function keys(): array {}

    public function values(): array {}

    public function toList(): array {}

    public function filter(\Closure $predicate): Map|Set|array {}

    public function map(\Closure $transform): array {}

    public function flatMap(\Closure $transform): array {}

    public function sumOf(\Closure $selector): int|float {}

    public function first(\Closure $predicate): mixed {}

    public function any(\Closure $predicate): bool {}

    public function groupBy(\Closure $key, ?string $typeArguments = null): Map {}

    public function associateBy(\Closure $key, ?string $typeArguments = null): Map {}

    public function sortedBy(\Closure $selector, ?\Closure $comparison = null): array {}
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
final class Container
{
    public function get(string $id): mixed {}

    public function has(string $id): bool {}
}

final class ContainerError extends \Error
{
}

/**
 * @strict-properties
 * @not-serializable
 */
final class Listening
{
    private function __construct() {}
}

namespace Sharp\Internal;

/**
 * @strict-properties
 * @not-serializable
 */
final class Listener
{
    private function __construct() {}

    private function hear(object $event): void {}
}

function accepts(string $type, mixed &$value, string $path, array &$problems): bool {}

function &statics(string $class, string $key, ?string $field = null): array {}

function keep(string $key, ?\Closure $compute = null): mixed {}

function equals(mixed $value, mixed $other): bool {}

function arrayOf(mixed $value, mixed $receiver = null): mixed {}

function readModule(string $path): void {}

function testModule(string $class, array $bindings): void {}

function startTest(string $class): void {}

function endTest(): void {}

function runScoped(\Closure $body): mixed {}

function building(object $object): bool {}

function build(object $object, \Closure $body, mixed ...$arguments): mixed {}

function emit(object $event): void {}

function listenersFor(object $event): array {}

function listen(string $event, \Closure $listener): \Sharp\Listening {}

function add(array &$list, mixed $value): void {}

function insert(array &$list, int $index, mixed $value): void {}

function set(array &$list, int $index, mixed $value): mixed {}

function remove(array &$list, mixed $value): bool {}

function removeAt(array &$list, int $index): mixed {}

function removeLast(array &$list): mixed {}

function clear(array|\Sharp\Map|\Sharp\Set &$collection): void {}

function get(array|\Sharp\Map $collection, mixed $key): mixed {}

function size(\Sharp\Map|\Sharp\Set $table): int {}

function containsKey(\Sharp\Map|\Sharp\Set $table, mixed $key): bool {}

function removeKey(\Sharp\Map|\Sharp\Set &$table, mixed $key): bool {}

function addKey(\Sharp\Set &$table, mixed $key): bool {}

function keys(\Sharp\Map|\Sharp\Set $table): array {}

function values(\Sharp\Map $map): array {}

function pairs(\Sharp\Map $map): array {}

function contains(array $list, mixed $value): bool {}

function indexOf(array $list, mixed $value): ?int {}

function nonEmpty(array $list): array {}

function entries(array $list, ?string $type = null): \Sharp\Map {}

function toSet(array $list, ?string $type = null): \Sharp\Set {}

function filter(array $list, \Closure $predicate): array {}

function filterEntries(\Sharp\Map $map, \Closure $predicate): \Sharp\Map {}

function filterElements(\Sharp\Set $set, \Closure $predicate): \Sharp\Set {}

function map(array|\Sharp\Map|\Sharp\Set $collection, \Closure $transform): array {}

function flatMap(array $list, \Closure $transform): array {}

function sumOf(array|\Sharp\Map|\Sharp\Set $collection, \Closure $selector): int|float {}

function first(array|\Sharp\Map|\Sharp\Set $collection, \Closure $predicate): mixed {}

function any(array|\Sharp\Map|\Sharp\Set $collection, \Closure $predicate): bool {}

function groupBy(array|\Sharp\Map|\Sharp\Set $collection, \Closure $key, ?string $type = null): \Sharp\Map {}

function associateBy(array|\Sharp\Map|\Sharp\Set $collection, \Closure $key, ?string $type = null): \Sharp\Map {}

function sortedBy(array|\Sharp\Map|\Sharp\Set $collection, \Closure $selector, ?\Closure $comparison = null): array {}
