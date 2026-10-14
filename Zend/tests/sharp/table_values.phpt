--TEST--
A Map or a Set is a value: every write through a holder copies a shared one first, so no other holder sees it
--FILE--
<?php
use Sharp\Map;
use Sharp\Set;

require __DIR__ . '/Guarded.sharp';

final class Holder
{
    public static Map $shared;
    public Map $prices;
}

function write(Map $prices): Map
{
    $prices['tea'] = 9;
    return $prices;
}

// Each row changes a copy and prints the original, then the copy.
$rows = [
    'assign' => function () {
        $prices = new Map(['tea', 3]);
        $copy = $prices;
        $copy['tea'] = 4;
        return [$prices, $copy];
    },
    'plain PHP function' => function () {
        $prices = new Map(['tea', 3]);
        $written = write($prices);
        return [$prices, $written];
    },
    'compound assign' => function () {
        $prices = new Map(['tea', 3]);
        $copy = $prices;
        $copy['tea'] += 2;
        return [$prices, $copy];
    },
    'unset' => function () {
        $prices = new Map(['tea', 3]);
        $copy = $prices;
        unset($copy['tea']);
        return [$prices, $copy];
    },
    'append' => function () {
        $tags = new Set(['a']);
        $copy = $tags;
        $copy[] = 'b';
        return [$tags, $copy];
    },
    'nested write' => function () {
        $lists = new Map(['a', [1]]);
        $copy = $lists;
        $copy['a'][] = 2;
        $copy['b'][] = 3;
        return [$lists, $copy];
    },
    'property' => function () {
        $holder = new Holder();
        $holder->prices = new Map(['tea', 3]);
        $kept = $holder->prices;
        $holder->prices['tea'] = 4;
        return [$kept, $holder->prices];
    },
    'static property' => function () {
        Holder::$shared = new Map(['tea', 3]);
        $kept = Holder::$shared;
        Holder::$shared['tea'] = 4;
        return [$kept, Holder::$shared];
    },
    'PHP# static property' => fn () => Demo\Shared::write(),
    'write in a loop' => function () {
        $prices = new Map(['tea', 3, 'jam', 5]);
        $seen = [];
        foreach ($prices as $name => $price) {
            $prices['bun'] = 1;
            $seen[] = $name;
        }
        return [new Set($seen), $prices];
    },
];

// An index write or unset through a PHP# property plain PHP can't write raises PHP's own error, as for an array.
$guarded = new Demo\Guarded();
$tag = new Demo\Wristband(new Map(['a', 1]));
$refused = [
    'get-only property' => function () use ($guarded) { $guarded->getOnly['b'] = 2; },
    'init property' => function () use ($guarded) { $guarded->initOnly['b'] = 2; },
    'hooked property' => function () use ($guarded) { $guarded->hooked['b'] = 2; },
    'unset on a hooked property' => function () use ($guarded) { unset($guarded->hooked['a']); },
    'property with a set hook alone' => function () use ($guarded) { $guarded->setHooked['b'] = 2; },
    'struct property' => function () use ($tag) { $tag->counts['b'] = 2; },
    // A property named at run time is never cached, so each write reaches the object's own read handler.
    'get-only property by a name' => function () use ($guarded) { $name = 'getOnly'; $guarded->$name['b'] = 2; },
    'init property by a name' => function () use ($guarded) { $name = 'initOnly'; unset($guarded->$name['a']); },
];

// Each round runs every row, so the JIT variants compile them before the last round, whose output is printed.
for ($round = 0; $round < 100; $round++) {
    ob_start();
    foreach ($rows as $name => $row) {
        [$original, $changed] = $row();
        echo $name, ': ', json_encode($original), ' ', json_encode($changed), "\n";
    }
    foreach ($refused as $name => $write) {
        try {
            $write();
            echo $name, ": written\n";
        } catch (Error $e) {
            echo $name, ': ', $e->getMessage(), "\n";
        }
    }
    echo 'unchanged: ', json_encode([$guarded->getOnly, $guarded->initOnly, $guarded->hooked, $guarded->setHooked, $tag->counts]), "\n";
    $output = ob_get_clean();
}
echo $output;
?>
--EXPECT--
assign: {"tea":3} {"tea":4}
plain PHP function: {"tea":3} {"tea":9}
compound assign: {"tea":3} {"tea":5}
unset: {"tea":3} {}
append: ["a"] ["a","b"]
nested write: {"a":[1]} {"a":[1,2],"b":[3]}
property: {"tea":3} {"tea":4}
static property: {"tea":3} {"tea":4}
PHP# static property: {"a":1} {"a":2,"b":3}
write in a loop: ["tea","jam"] {"tea":3,"jam":5,"bun":1}
get-only property: Cannot indirectly modify readonly property Demo\Guarded::$getOnly
init property: Cannot indirectly modify protected(set) property Demo\Guarded::$initOnly from global scope
hooked property: Indirect modification of Demo\Guarded::$hooked is not allowed
unset on a hooked property: Indirect modification of Demo\Guarded::$hooked is not allowed
property with a set hook alone: Indirect modification of Demo\Guarded::$setHooked is not allowed
struct property: Cannot indirectly modify readonly property Demo\Wristband::$counts
get-only property by a name: Cannot indirectly modify readonly property Demo\Guarded::$getOnly
init property by a name: Cannot indirectly modify protected(set) property Demo\Guarded::$initOnly from global scope
unchanged: [{"a":1},{"a":1},{"a":1},{"a":1},{"a":1}]
