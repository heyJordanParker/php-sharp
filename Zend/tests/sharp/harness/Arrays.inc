<?php

// Plain PHP functions a PHP# Map or Set crosses into, each saying what it received.
function array_keys_of(array $values): string
{
    return get_debug_type($values) . ' ' . json_encode(array_keys($values));
}

/** @param array<array-key, mixed> $values */
function documented_keys_of($values): string
{
    return get_debug_type($values) . ' ' . json_encode(array_keys($values));
}

function type_of_mixed(mixed $value): string
{
    return get_debug_type($value);
}

function type_of_untyped($value): string
{
    return get_debug_type($value);
}

function type_of_iterable(iterable $value): string
{
    return get_debug_type($value);
}

function write_array(array $prices): void
{
    $prices['tea'] = 9;
}

function write_mixed(mixed $prices): void
{
    $prices['tea'] = 9;
}

/** @return array<string, int> */
function price_list(): array
{
    return ['tea' => 3, '5' => 1];
}

/** @return array<int, string> */
function shelf_names(): array
{
    return [4 => 'jam', 8 => 'tea'];
}

/** @return Generator<int, array<string, int>> */
function price_rows(): Generator
{
    yield ['a' => 1];
    yield ['b' => 2, 'c' => 3];
}

// Plain PHP functions whose parameter names an object type a Sharp\Map or a Sharp\Set has.
function count_of_countable(Countable $values): string
{
    return get_debug_type($values) . ' ' . count($values);
}

function count_of_traversable(Traversable $values): string
{
    return get_debug_type($values) . ' ' . iterator_count($values);
}

function json_of(JsonSerializable $values): string
{
    return get_debug_type($values) . ' ' . json_encode($values);
}

function type_of_object(object $value): string
{
    return get_debug_type($value);
}

function count_of_map(Sharp\Map $values): string
{
    return get_debug_type($values) . ' ' . count($values);
}

/**
 * @template T of Countable
 * @param T $values
 */
function count_of_bounded_countable($values): string
{
    return get_debug_type($values) . ' ' . count($values);
}

/** @return Closure(array): string */
function array_counter(): Closure
{
    return static fn (array $counts): string => get_debug_type($counts) . ' ' . count($counts);
}

// A lazy plain PHP source that counts the names it gave, so a test sees how far a PHP# loop read it. Its docblock
// promises strings, which `$last` need not be.
final class Names
{
    public static int $given = 0;
}

/** @return iterable<string> */
function names_and(mixed $last): iterable
{
    foreach (['tea', 'jam', $last, 'pie'] as $name) {
        Names::$given++;
        yield $name;
    }
}

// The same source as a generator, an IteratorAggregate and a Laravel LazyCollection twin, each typed by its own class.
/** @return Generator<int, string> */
function names_generator(mixed $last): Generator
{
    yield from names_and($last);
}

/**
 * @template TKey of array-key
 * @template TValue
 *
 * @implements IteratorAggregate<TKey, TValue>
 */
final class NameRows implements IteratorAggregate
{
    public function __construct(private mixed $last)
    {
    }

    /** @return Generator<TKey, TValue> */
    public function getIterator(): Generator
    {
        yield from names_and($this->last);
    }
}

/** @return NameRows<int, string> */
function name_rows(mixed $last): NameRows
{
    return new NameRows($last);
}

/**
 * @template TKey of array-key
 * @template-covariant TValue
 *
 * @extends IteratorAggregate<TKey, TValue>
 */
interface Enumerable extends IteratorAggregate
{
}

/**
 * @template TKey of array-key
 * @template-covariant TValue
 *
 * @implements Enumerable<TKey, TValue>
 */
final class LazyCollection implements Enumerable
{
    public function __construct(private mixed $last)
    {
    }

    /** @return Generator<TKey, TValue> */
    public function getIterator(): Generator
    {
        yield from names_and($this->last);
    }

    public function count(): int
    {
        return iterator_count($this->getIterator());
    }
}

/** @return LazyCollection<int, string> */
function lazy_names(mixed $last): LazyCollection
{
    return new LazyCollection($last);
}

// A plain PHP object a call runs through its __invoke, whose parameter names array.
final class ArrayCounter
{
    public function __invoke(array $counts): string
    {
        return get_debug_type($counts) . ' ' . count($counts);
    }
}

function send_headers(array $headers): string
{
    return json_encode($headers);
}

// A plain PHP attribute whose argument names array, which a PHP# Map argument fills.
#[Attribute(Attribute::TARGET_ALL)]
final class Label
{
    public function __construct(public string $text, public array $config = [])
    {
    }
}

class Shouter
{
    public string $word = 'b' {
        get => strtoupper($this->word);
    }
}

class Hiding
{
    protected $hidden = [];

    public function hide(string $name): void
    {
        $this->hidden[] = $name;
    }
}

// A plain PHP object PHP# fills, as a request context, a DTO or a job is: each property declares a type a parameter
// could, and one declares none.
class RequestContext
{
    public array $payload = [];

    public ?array $extra = null;

    public $untyped;

    public mixed $anything = null;

    public iterable $items = [];

    public array|Countable $either = [];

    public ?Countable $counted = null;

    public static array $registry = [];

    public array $payloads = [];

    /** @var list<mixed> */
    public array $rows = ['seed'];
}

// A plain PHP parent whose properties a PHP# subclass writes, as a Laravel job's base class is.
abstract class BaseJob
{
    public array $payload = [];

    public array $payloads = [];

    public static array $registry = [];

    /** @var list<array<string, mixed>> */
    public array $rows = [['seed' => 0]];

    /** @var array<int, string> */
    public array $names = [3 => 'ada', 7 => 'bo'];
}

// A plain PHP class that takes a write to a name it declares no property for through __set, as Eloquent's Model does,
// and records what each write gave it.
final class Attributes
{
    public array $seen = [];

    public function __set(string $name, mixed $value): void
    {
        $this->seen[$name] = is_array($value) ? $value : get_debug_type($value);
    }
}

// A plain PHP parent whose methods declare no return type, as Eloquent's Model::getKey() declares none.
class Record
{
    public function getKey()
    {
        return 7;
    }

    public function getMorphClass()
    {
        return 'record';
    }
}
