<?php

use Demo\Spot;
use Sharp\Map;
use Sharp\Set;

enum Tone
{
    case Warm;
    case Cool;
}

// == through op_Equality with its hash(), as a PHP# class that declares operator == lowers to.
final class Cents
{
    public function __construct(public int $amount, public string $label) {}

    public static function op_Equality(mixed $a, mixed $b): bool
    {
        return $a->amount === $b->amount;
    }

    public function hash(): int
    {
        return $this->amount;
    }
}

// Every value hashes alike, so each key after the first sits in one chain.
final class Clash
{
    public function __construct(public int $id) {}

    public static function op_Equality(mixed $a, mixed $b): bool
    {
        return $a->id === $b->id;
    }

    public function hash(): int
    {
        return 7;
    }
}

// Counts its == calls. Amounts 1 and 9 share a chain of the first capacity of 8, under different hashes.
final class Counted
{
    public static int $compared = 0;

    public function __construct(public int $amount) {}

    public static function op_Equality(mixed $a, mixed $b): bool
    {
        self::$compared++;
        return $a->amount === $b->amount;
    }

    public function hash(): int
    {
        return $this->amount;
    }
}

// Two sibling classes that inherit one declared ==, and a third that declares its own with the same body.
abstract class Party
{
    public function __construct(public int $id) {}

    public static function op_Equality(mixed $a, mixed $b): bool
    {
        return $a->id === $b->id;
    }

    public function hash(): int
    {
        return $this->id;
    }
}

final class Guest extends Party
{
}

final class Host extends Party
{
}

final class Stranger
{
    public function __construct(public int $id) {}

    public static function op_Equality(mixed $a, mixed $b): bool
    {
        return $a->id === $b->id;
    }

    public function hash(): int
    {
        return $this->id;
    }
}

final class Plain
{
}

function show(mixed $key): string
{
    return match (true) {
        $key instanceof UnitEnum => $key->name,
        $key instanceof Map, $key instanceof Set => get_class($key) . json_encode($key),
        is_object($key) => get_class($key) . json_encode(get_object_vars($key)),
        default => var_export($key, true),
    };
}

// Each row writes its keys in order and prints what the Map holds: each stored key with its value.
function held(array $keysAndValues): string
{
    $map = new Map();
    for ($at = 0; $at < count($keysAndValues); $at += 2) {
        $map[$keysAndValues[$at]] = $keysAndValues[$at + 1];
    }
    $held = [];
    foreach ($map as $key => $value) {
        $held[] = show($key) . ' => ' . $value;
    }
    return implode(', ', $held);
}

// What each table_keys*.phpt test prints.
function table_keys(): string
{
    $plain = new Plain();
    $rows = [
        'an int and its float' => fn () => held([5, 'a', 5.0, 'b']),
        'zero and minus zero' => fn () => held([0.0, 'a', -0.0, 'b', 0, 'c']),
        'NAN is one key' => fn () => held([NAN, 'a', NAN, 'b']),
        'a numeric string is not an int' => fn () => held([5, 'a', '5', 'b']),
        'true, false and null' => fn () => held([true, 'a', false, 'b', null, 'c', true, 'd', 1, 'e']),
        'an enum case' => fn () => held([Tone::Warm, 'a', Tone::Cool, 'b', Tone::Warm, 'c']),
        'a declared ==' => fn () => held([new Cents(5, 'first'), 'a', new Cents(5, 'second'), 'b', new Cents(6, 'third'), 'c']),
        'a struct, by its whole value' => fn () => held([new Spot(1, 2), 'a', new Spot(1, 2), 'b', new Spot(2, 1), 'c']),
        'siblings share the == they inherit' => fn () => held([new Guest(1), 'a', new Host(1), 'b', new Host(2), 'c']),
        'an unrelated class with its own ==' => fn () => held([new Guest(1), 'a', new Stranger(1), 'b']),
        'a chain of equal hashes' => fn () => held([new Clash(1), 'a', new Clash(2), 'b', new Clash(3), 'c', new Clash(2), 'd']),
        'an object without ==' => fn () => held([$plain, 'a', new Plain(), 'b', $plain, 'c']),
        'a List' => fn () => held([[1, 2], 'a', [1, 2.0], 'b', [2, 1], 'c']),
        'a Map, regardless of order' => fn () => held([new Map([1, 'x', 2, 'y']), 'a', new Map([2, 'y', 1, 'x']), 'b']),
        'a Set, regardless of order' => fn () => held([new Set([1, 2]), 'a', new Set([2, 1]), 'b', new Set([1]), 'c']),
    ];

    ob_start();
    foreach ($rows as $name => $row) {
        echo $name, ': ', $row(), "\n";
    }
    // Each Clash goes in at the head of the one chain, so removing 2 unlinks a middle bucket and removing 1 the last.
    $chain = new Map([new Clash(1), 'a', new Clash(2), 'b', new Clash(3), 'c']);
    unset($chain[new Clash(2)]);
    echo 'a removal inside a chain: ', $chain[new Clash(1)], $chain[new Clash(3)], var_export($chain[new Clash(2)], true);
    unset($chain[new Clash(1)]);
    echo ' ', $chain[new Clash(3)], var_export($chain[new Clash(1)], true), ' ', count($chain), "\n";
    $counted = new Map([new Counted(1), 'a', new Counted(9), 'b']);
    Counted::$compared = 0;
    echo 'a key under another hash is never compared: ', $counted[new Counted(1)], ' ', Counted::$compared;
    $counted = new Map([[new Counted(1)], 'a', [new Counted(2)], 'b']);
    Counted::$compared = 0;
    echo ', in a List: ', $counted[[new Counted(1)]], ' ', Counted::$compared, "\n";
    $set = new Set([5, 5.0, '5', Tone::Warm, Tone::Warm]);
    echo 'a Set: ', count($set), ' ', var_export(isset($set[5.0]), true), ' ', var_export(isset($set['6']), true), "\n";
    // Every NAN is one key, whatever its sign and payload bits, as C#'s Equals finds it (spec section 12), and -0.0 and
    // 0.0 are one key.
    $nan = new Set([NAN, -NAN, fdiv(0, 0), 1.0]);
    $nanMap = new Map([NAN, 'a', -NAN, 'b']);
    $zeros = new Set([0.0, -0.0, 0]);
    echo 'NAN in a Set: ', count($nan), ' ', var_export(isset($nan[NAN]), true), ', in a Map: ', count($nanMap), ' ',
        var_export($nanMap[fdiv(0, 0)], true), ', zeros: ', count($zeros), "\n";
    // An int and a float are one key only when the float is exactly that whole number.
    $big = new Set([2 ** 53 + 1, 2.0 ** 53, 2 ** 53, 7, 7.0, 7.5]);
    echo 'ints and floats: ', count($big), ' ', var_export(isset($big[2.0 ** 53 + 2]), true), "\n";
    $prices = new Map([new Cents(5, 'first'), 'tea']);
    echo 'a read: ', $prices[new Cents(5, 'other')], ' ', var_export($prices[new Cents(6, 'none')], true), "\n";
    try {
        $prices[[1, fopen('php://memory', 'r')]] = 'x';
    } catch (TypeError $e) {
        echo 'a resource: ', $e->getMessage(), "\n";
    }

    return ob_get_clean();
}

// Each round runs every row, so the JIT variants compile them before the last round, whose output is printed.
function table_keys_rounds(): string
{
    for ($round = 0; $round < 100; $round++) {
        $output = table_keys();
    }

    return $output;
}
