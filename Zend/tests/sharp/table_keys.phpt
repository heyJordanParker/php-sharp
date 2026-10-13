--TEST--
A Map finds a key, and a Set an element, by PHP#'s ==, and keeps the key it first stored
--FILE--
<?php
use Demo\Spot;
use Sharp\Map;
use Sharp\Set;

require __DIR__ . '/Spot.sharp';

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

$plain = new Plain();
$rows = [
    'an int and its float' => fn () => held([5, 'a', 5.0, 'b']),
    'zero and minus zero' => fn () => held([0.0, 'a', -0.0, 'b', 0, 'c']),
    'NAN equals nothing' => fn () => held([NAN, 'a', NAN, 'b']),
    'a numeric string is not an int' => fn () => held([5, 'a', '5', 'b']),
    'true, false and null' => fn () => held([true, 'a', false, 'b', null, 'c', true, 'd', 1, 'e']),
    'an enum case' => fn () => held([Tone::Warm, 'a', Tone::Cool, 'b', Tone::Warm, 'c']),
    'a declared ==' => fn () => held([new Cents(5, 'first'), 'a', new Cents(5, 'second'), 'b', new Cents(6, 'third'), 'c']),
    'a struct, by its whole value' => fn () => held([new Spot(1, 2), 'a', new Spot(1, 2), 'b', new Spot(2, 1), 'c']),
    'a chain of equal hashes' => fn () => held([new Clash(1), 'a', new Clash(2), 'b', new Clash(3), 'c', new Clash(2), 'd']),
    'an object without ==' => fn () => held([$plain, 'a', new Plain(), 'b', $plain, 'c']),
    'a List' => fn () => held([[1, 2], 'a', [1, 2.0], 'b', [2, 1], 'c']),
    'a Map, regardless of order' => fn () => held([Map::of([1, 'x', 2, 'y']), 'a', Map::of([2, 'y', 1, 'x']), 'b']),
    'a Set, regardless of order' => fn () => held([Set::from([1, 2]), 'a', Set::from([2, 1]), 'b', Set::from([1]), 'c']),
];

// Each round runs every row, so the JIT variants compile them before the last round, whose output is printed.
for ($round = 0; $round < 100; $round++) {
    ob_start();
    foreach ($rows as $name => $row) {
        echo $name, ': ', $row(), "\n";
    }
    // Each Clash goes in at the head of the one chain, so removing 2 unlinks a middle bucket and removing 1 the last.
    $chain = Map::of([new Clash(1), 'a', new Clash(2), 'b', new Clash(3), 'c']);
    unset($chain[new Clash(2)]);
    echo 'a removal inside a chain: ', $chain[new Clash(1)], $chain[new Clash(3)], var_export($chain[new Clash(2)], true);
    unset($chain[new Clash(1)]);
    echo ' ', $chain[new Clash(3)], var_export($chain[new Clash(1)], true), ' ', count($chain), "\n";
    $counted = Map::of([new Counted(1), 'a', new Counted(9), 'b']);
    Counted::$compared = 0;
    echo 'a key under another hash is never compared: ', $counted[new Counted(1)], ' ', Counted::$compared;
    $counted = Map::of([[new Counted(1)], 'a', [new Counted(2)], 'b']);
    Counted::$compared = 0;
    echo ', in a List: ', $counted[[new Counted(1)]], ' ', Counted::$compared, "\n";
    $set = Set::from([5, 5.0, '5', Tone::Warm, Tone::Warm]);
    echo 'a Set: ', count($set), ' ', var_export(isset($set[5.0]), true), ' ', var_export(isset($set['6']), true), "\n";
    $prices = Map::of([new Cents(5, 'first'), 'tea']);
    echo 'a read: ', $prices[new Cents(5, 'other')], ' ', var_export($prices[new Cents(6, 'none')], true), "\n";
    try {
        $prices[[1, fopen('php://memory', 'r')]] = 'x';
    } catch (TypeError $e) {
        echo 'a resource: ', $e->getMessage(), "\n";
    }
    $output = ob_get_clean();
}
echo $output;
?>
--EXPECT--
an int and its float: 5 => b
zero and minus zero: 0.0 => c
NAN equals nothing: NAN => a, NAN => b
a numeric string is not an int: 5 => a, '5' => b
true, false and null: true => d, false => b, NULL => c, 1 => e
an enum case: Warm => c, Cool => b
a declared ==: Cents{"amount":5,"label":"first"} => b, Cents{"amount":6,"label":"third"} => c
a struct, by its whole value: Demo\Spot{"x":1,"y":2} => b, Demo\Spot{"x":2,"y":1} => c
a chain of equal hashes: Clash{"id":1} => a, Clash{"id":2} => d, Clash{"id":3} => c
an object without ==: Plain[] => c, Plain[] => b
a List: array (
  0 => 1,
  1 => 2,
) => b, array (
  0 => 2,
  1 => 1,
) => c
a Map, regardless of order: Sharp\Map{"1":"x","2":"y"} => b
a Set, regardless of order: Sharp\Set[1,2] => b, Sharp\Set[1] => c
a removal inside a chain: acNULL cNULL 1
a key under another hash is never compared: a 1, in a List: a 1
a Set: 3 true false
a read: tea NULL
a resource: A Map key or a Set element can't be of type resource
