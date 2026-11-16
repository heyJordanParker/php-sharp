<?php

/** @generate-class-entries */

namespace Sharp;

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

/**
 * @strict-properties
 * @not-serializable
 */
final class Listener
{
    private function __construct() {}

    private function hear(object $event): void {}
}

function &statics(string $class, string $key, ?string $field = null): array {}

function keep(string $key, ?\Closure $compute = null): mixed {}

function equals(mixed $value, mixed $other): bool {}

function jsonEncode(mixed $value, int $flags, int $indent): string {}

function jsonDecode(string $json): mixed {}

function parseAs(string $type, mixed $value, bool $text): mixed {}

function parseVariant(string $family, mixed $value, bool $throws = true): ?object {}

function withTag(string $family, string $tag): ?string {}

function resolve(string $host): array {}

function processorCount(): int {}

function compare(mixed $left, mixed $right): int {}

function readGlobal(string $name, ?string $key = null): mixed {}

function writeGlobal(string $name, mixed $value, ?string $key = null): void {}

function includeGlobal(string $path): void {}

function accessors(string $type, string $name): array {}

function createLazy(string $type, callable $initialize): object {}

function arrayOf(mixed $value, mixed $receiver = null): mixed {}

function returned(mixed $result, object|string|null $implementer, string $method, string $types, string $target): mixed {}

function elements(object $object, bool $pairs): Sequence {}

function properties(string $type): array {}

function methods(string $type): array {}

function constructor(string $type): ?array {}

function initial(string $class, string $name): ?\Closure {}

function typeArguments(string $type): array {}

function typeKind(string $type): string {}

function isNullable(string $type): bool {}

function nonNullable(string $type): string {}

function typeParts(string $type): array {}

function tupleNames(string $type): array {}

function genericDefinition(string $type): ?string {}

function typeParameters(string $type): array {}

function baseType(string $type): ?string {}

function interfaces(string $type): array {}

function classFacts(string $type): array {}

function enumBackingType(string $type): ?string {}

function makeGenericType(string $type, array $arguments): string {}

function enumCases(string $type): array {}

function constants(string $type): array {}

function constant(string $class, string $name): mixed {}

function createInstance(string $type, array $arguments): object {}

function invoke(?object $receiver, string $class, string $method, array $arguments, string $readOn): mixed {}

function read(?object $receiver, string $class, string $property): mixed {}

function write(?object $receiver, string $class, string $property, mixed $value, bool $beforeLoad = false): void {}

function readField(?object $receiver, string $class, string $field): mixed {}

function writeField(?object $receiver, string $class, string $field, mixed $value): void {}

function readModule(string $path): void {}

function testModule(string $class, array $bindings): void {}

function startTest(string $class): void {}

function endTest(): void {}

function runScoped(\Closure $body): mixed {}

function building(object $object): bool {}

function build(object $object, \Closure $body, mixed ...$arguments): mixed {}

function emit(object $event, string $dispatcher): void {}

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

function put(\Sharp\Map &$map, mixed $key, mixed $value): void {}

function getOrPut(\Sharp\Map &$map, mixed $key, \Closure $defaultValue): mixed {}

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

function firstOrNull(array|\Sharp\Map|\Sharp\Set $collection, \Closure $predicate): mixed {}

function sortedBy(array|\Sharp\Map|\Sharp\Set $collection, \Closure $selector, \Closure|array|null $order = null): array {}

function sortedByDescending(array|\Sharp\Map|\Sharp\Set $collection, \Closure $selector, \Closure|array|null $order = null): array {}

function sortedWith(array $list, \Closure $comparison): array {}

function minBy(array|\Sharp\Map|\Sharp\Set $collection, \Closure $selector, \Closure|array|null $order = null): mixed {}

function maxBy(array|\Sharp\Map|\Sharp\Set $collection, \Closure $selector, \Closure|array|null $order = null): mixed {}
