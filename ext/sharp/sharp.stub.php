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

    public function entries(): Map {}

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

    public function sumOf(\Closure $selector): int|float {}

    public function first(\Closure $predicate): mixed {}

    public function any(\Closure $predicate): bool {}

    public function groupBy(\Closure $key): Map {}

    public function associateBy(\Closure $key): Map {}

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

    public static function scope(\Closure $run): mixed {}
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

function keep(string $key, \Closure $compute): mixed {}

function equals(mixed $value, mixed $other): bool {}

function readModule(string $path): void {}

function testModule(array $classes, array $bindings): void {}

function startTest(string $class): void {}

function endTest(): void {}

function building(object $object): bool {}

function build(object $object, \Closure $body, mixed ...$arguments): mixed {}

function emit(object $event): void {}

function listenersFor(object $event): array {}

function listen(string $event, \Closure $listener): \Sharp\Listening {}
