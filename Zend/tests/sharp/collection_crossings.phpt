--TEST--
A Map or a Set crosses into a plain PHP parameter that names array as a PHP array, and into one that names an object type as the object, a PHP array crosses back into a Map, a PHP list enters a tuple when its length and elements match, and a Map enters an Iterable as its entries
--FILE--
<?php
require __DIR__ . '/harness/Arrays.inc';
require __DIR__ . '/Crossings.sharp';

use Demo\Crossings;
use Sharp\Map;
use Sharp\Set;

function show(Map $map): string
{
    $entries = [];
    foreach ($map as $key => $value) {
        $entries[] = var_export($key, true) . ' => ' . $value;
    }
    return implode(', ', $entries);
}

// Each round runs every row, so the JIT variants compile them before the last round, whose output is printed.
for ($round = 0; $round < 100; $round++) {
    ob_start();
    echo implode("\n", Crossings::outward(new Map(['tea', 3, '5', 1]), new Set(['a', 'b']))), "\n";
    $prices = new Map(['tea', 3]);
    echo 'a plain PHP write leaves the Map: ', show(Crossings::written($prices)), ' / ', show($prices), "\n";
    echo 'a plain PHP result into a Map local: ', show(Crossings::local()), "\n";
    echo 'a plain PHP result into a Map return: ', show(Crossings::returned()), "\n";
    echo 'a PHP array into a Map<string, int> parameter: ', show(Crossings::received(['5' => 1, 'x' => 2])), "\n";
    echo implode("\n", Crossings::objects(new Map(['tea', 3, '5', 1]), new Set(['a', 'b']))), "\n";
    echo 'a plain PHP closure a local holds: ', Crossings::counted(new Map(['a', 1, 'b', 2])), "\n";
    echo 'a plain PHP object a local holds: ', Crossings::invoked(new Map(['a', 1, 'b', 2])), "\n";
    echo 'a PHP# function type passes the object: ', Crossings::applied(fn ($values) => get_debug_type($values), new Map(['a', 1])), "\n";
    echo 'a PHP array into a Map<string|Level, int> parameter: ', json_encode(Crossings::named(['5' => 1])), "\n";
    // A lazy plain PHP source is checked element by element as a loop reads it, so a bad element throws at the
    // read and not before, and the source is never read ahead.
    Names::$given = 0;
    echo 'a lazy plain PHP source into an Iterable<string> parameter: ', Crossings::joined(names_and('cake')), Names::$given, "\n";
    $reads = [
        fn () => Crossings::joined(names_and(3)),
        fn () => Crossings::lazilyReturned(3),
        fn () => Crossings::heldGenerator(3),
        fn () => Crossings::heldRows(3),
        fn () => Crossings::joined(Crossings::returnedLazy(3)),
        fn () => Crossings::passedGenerator(3),
    ];
    foreach ($reads as $read) {
        Names::$given = 0;
        try {
            $read();
        } catch (TypeError $e) {
            echo get_class($e), ': ', $e->getMessage(), ', after ', Names::$given, " names\n";
        }
    }
    echo 'a plain PHP class keeps its own methods: ', Crossings::lazyCount(3), "\n";
    // A tuple from plain PHP is a PHP list of exactly its elements, each of its type.
    echo 'a PHP list into a [string name, int count] parameter: ', Crossings::pair(['tea', 3]), "\n";
    foreach ([['tea'], ['tea', 'x'], ['name' => 'tea', 'count' => 3]] as $given) {
        try {
            Crossings::pair($given);
        } catch (TypeError $e) {
            echo get_class($e), ': ', $e->getMessage(), "\n";
        }
    }
    // A Map is an Iterable of its entries, from plain PHP and from PHP#, and a loop variable over it is each entry.
    echo 'a Map into an Iterable<[string, int]> parameter: ', Crossings::entries(new Map(['tea', 3, 'pie', 7])), "\n";
    echo 'a list of tuples into an Iterable<[string, int]> parameter: ', Crossings::entries([['tea', 3]]), "\n";
    echo 'a Map a PHP# call passes on: ', Crossings::ownEntries(['tea' => 3, 'pie' => 7]), "\n";
    try {
        Crossings::entries(new Map(['tea', 'x']));
    } catch (TypeError $e) {
        echo get_class($e), ': ', $e->getMessage(), "\n";
    }
    $output = ob_get_clean();
}
echo $output;
?>
--EXPECTF--
array ["tea",5]
array [0,1]
array ["tea",5]
Sharp\Map
Sharp\Map
Sharp\Set
a plain PHP write leaves the Map: 'tea' => 3 / 'tea' => 3
a plain PHP result into a Map local: 'tea' => 3, '5' => 1, 'jam' => 5
a plain PHP result into a Map return: 'tea' => 3, '5' => 1
a PHP array into a Map<string, int> parameter: '5' => 1, 'x' => 2
Sharp\Map 2
Sharp\Set 2
Sharp\Map {"tea":3,"5":1}
Sharp\Set
Sharp\Map 2
a plain PHP closure a local holds: array 2
a plain PHP object a local holds: array 2
a PHP# function type passes the object: Sharp\Map
a PHP array into a Map<string|Level, int> parameter: ["1","1","true"]
a lazy plain PHP source into an Iterable<string> parameter: tea jam cake pie 4
TypeError: Demo\Crossings::joined(): Argument #1 ($names) must be of type Iterable<string>, Generator given: [2] expected string, got int 3, after 3 names
TypeError: names_and(): Return value must be of type Iterable<string>, Generator returned: [2] expected string, got int 3, after 3 names
TypeError: names_generator(): Return value must be of type Iterable<string>, Generator returned: [2] expected string, got int 3, after 3 names
TypeError: name_rows(): Return value must be of type Iterable<string>, NameRows returned: [2] expected string, got int 3, after 3 names
TypeError: lazy_names(): Return value must be of type Iterable<string>, LazyCollection returned: [2] expected string, got int 3, after 3 names
TypeError: names_generator(): Return value must be of type Iterable<string>, Generator returned: [2] expected string, got int 3, after 3 names
a plain PHP class keeps its own methods: 4
a PHP list into a [string name, int count] parameter: tea 3
TypeError: Demo\Crossings::pair(): Argument #1 ($given) must be of type [string, int], array given, called in %s on line %d
TypeError: Demo\Crossings::pair(): Argument #1 ($given) must be of type [string, int], array given: [1] expected int, got string 'x', called in %s on line %d
TypeError: Demo\Crossings::pair(): Argument #1 ($given) must be of type [string, int], array given, called in %s on line %d
a Map into an Iterable<[string, int]> parameter: tea=3 pie=7 
a list of tuples into an Iterable<[string, int]> parameter: tea=3 
a Map a PHP# call passes on: tea:3 pie:7 tea=3 pie=7 
TypeError: Demo\Crossings::entries(): Argument #1 ($given) must be of type Iterable<[string, int]>, Sharp.Map given: [0][1] expected int, got string 'x', called in %s on line %d
