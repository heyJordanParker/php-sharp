--TEST--
A Map or a Set crosses to plain PHP as an object: built from an array, read back as one, and encoded as JSON
--FILE--
<?php
use Sharp\Map;
use Sharp\Set;

enum Size: string
{
    case Small = 's';
    case Large = 'l';
}

enum Tone
{
    case Warm;
}

function attempt(callable $call): string
{
    try {
        return json_encode($call());
    } catch (Throwable $e) {
        return get_class($e) . ': ' . $e->getMessage();
    }
}

$rows = [
    'new Map, a later key replaces' => fn () => new Map(['a', 1, 'b', 2, 'a', 3]),
    'new Map, an odd list' => fn () => new Map(['a', 1, 'b']),
    'new Map, keys and values not a list' => fn () => new Map(['a' => 1]),
    'from, PHP\'s keys' => fn () => Map::from(['5' => 1, 'x' => 2, 7 => 3]),
    'new Set, each element once' => fn () => new Set(['a', 'b', 'a', 1, 1.0]),
    'toArray' => fn () => new Map([1, 'a', 'b', 'c'])->toArray(),
    'toArray, a key no array holds' => fn () => new Map([Tone::Warm, 1])->toArray(),
    'Set toArray' => fn () => new Set(['a', 'b'])->toArray(),
    'keys, the stored keys' => fn () => array_map(fn ($key) => $key->name, new Map([Tone::Warm, 1])->keys()),
    'values' => fn () => new Map(['a', 1, 'b', 2])->values(),
    'toList' => fn () => new Set(['b', 'a'])->toList(),
    'count' => fn () => [count(new Map(['a', 1])), count(new Set()), new Set([1, 2])->count()],
    'an empty Map is an object' => fn () => new Map(),
    'an empty Set is a list' => fn () => new Set(),
    'a backed enum key' => fn () => new Map([Size::Small, 1, Size::Large, 2]),
    'a key JSON has no text for' => fn () => new Map([Tone::Warm, 1]),
    'a float key' => fn () => new Map([1.5, 1]),
    '== regardless of order' => fn () => [new Map(['a', 1, 'b', 2]) == new Map(['b', 2, 'a', 1]), new Map(['a', 1]) == new Map(['a', 2]), new Set([1]) == new Map([1, null])],
    'a Set has no index' => fn () => new Set(['a'])['a'],
    'a Set adds with []' => function () {
        $set = new Set();
        $set['a'] = true;
        return $set;
    },
    'a Map write needs a key' => function () {
        $map = new Map();
        $map[] = 1;
        return $map;
    },
    'isset and empty' => function () {
        $map = new Map(['a', 0, 'b', null]);
        return [isset($map['a']), empty($map['a']), isset($map['b']), isset($map['c']), isset(new Set([0])[0]), empty(new Set([0])[0])];
    },
    'a loop by reference' => function () {
        foreach (new Map(['a', 1]) as &$value) {
        }
    },
    'no dynamic property' => function () {
        $map = new Map();
        $map->extra = 1;
    },
    'not serializable' => fn () => serialize(new Map()),
    'a clone is equal and apart' => function () {
        $map = new Map(['a', 1]);
        $copy = clone $map;
        $copy['b'] = 2;
        return [$map, $copy];
    },
];

// Each round runs every row, so the JIT variants compile them before the last round, whose output is printed.
for ($round = 0; $round < 100; $round++) {
    ob_start();
    foreach ($rows as $name => $row) {
        echo $name, ': ', attempt($row), "\n";
    }
    var_dump(new Map([Tone::Warm, [1]]), new Set(['a']));
    $output = ob_get_clean();
}
echo $output;
?>
--EXPECTF--
new Map, a later key replaces: {"a":3,"b":2}
new Map, an odd list: ValueError: Sharp\Map::__construct(): Argument #1 ($keysAndValues) must be a list of keys each followed by its value
new Map, keys and values not a list: ValueError: Sharp\Map::__construct(): Argument #1 ($keysAndValues) must be a list of keys each followed by its value
from, PHP's keys: {"5":1,"x":2,"7":3}
new Set, each element once: ["a","b",1]
toArray: {"1":"a","b":"c"}
toArray, a key no array holds: ValueError: A PHP array key is an int or a string, and this Map holds a key of type Tone
Set toArray: ["a","b"]
keys, the stored keys: ["Warm"]
values: [1,2]
toList: ["b","a"]
count: [1,0,2]
an empty Map is an object: {}
an empty Set is a list: []
a backed enum key: {"s":1,"l":2}
a key JSON has no text for: JsonException: A JSON object key is text, and Tone is not
a float key: JsonException: A JSON object key is text, and float is not
== regardless of order: [true,false,false]
a Set has no index: Error: A Set has no index: test for an element with isset($set[$element])
a Set adds with []: Error: A Set adds an element with $set[] = $element
a Map write needs a key: Error: A Map write needs a key
isset and empty: [true,true,false,false,true,true]
a loop by reference: Error: A Map can't be looped over by reference
no dynamic property: Error: Cannot create dynamic property Sharp\Map::$extra
not serializable: Exception: Serialization of 'Sharp\Map' is not allowed
a clone is equal and apart: [{"a":1},{"a":1,"b":2}]
object(Sharp\Map)#%d (1) {
  [0]=>
  array(2) {
    [0]=>
    enum(Tone::Warm)
    [1]=>
    array(1) {
      [0]=>
      int(1)
    }
  }
}
object(Sharp\Set)#%d (1) {
  [0]=>
  string(1) "a"
}
