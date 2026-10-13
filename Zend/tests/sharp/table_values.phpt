--TEST--
A Map or a Set is a value: every write through a holder copies a shared one first, so no other holder sees it
--FILE--
<?php
use Sharp\Map;
use Sharp\Set;

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
        $prices = Map::of(['tea', 3]);
        $copy = $prices;
        $copy['tea'] = 4;
        return [$prices, $copy];
    },
    'plain PHP function' => function () {
        $prices = Map::of(['tea', 3]);
        $written = write($prices);
        return [$prices, $written];
    },
    'compound assign' => function () {
        $prices = Map::of(['tea', 3]);
        $copy = $prices;
        $copy['tea'] += 2;
        return [$prices, $copy];
    },
    'unset' => function () {
        $prices = Map::of(['tea', 3]);
        $copy = $prices;
        unset($copy['tea']);
        return [$prices, $copy];
    },
    'append' => function () {
        $tags = Set::from(['a']);
        $copy = $tags;
        $copy[] = 'b';
        return [$tags, $copy];
    },
    'nested write' => function () {
        $lists = Map::of(['a', [1]]);
        $copy = $lists;
        $copy['a'][] = 2;
        $copy['b'][] = 3;
        return [$lists, $copy];
    },
    'property' => function () {
        $holder = new Holder();
        $holder->prices = Map::of(['tea', 3]);
        $kept = $holder->prices;
        $holder->prices['tea'] = 4;
        return [$kept, $holder->prices];
    },
    'static property' => function () {
        Holder::$shared = Map::of(['tea', 3]);
        $kept = Holder::$shared;
        Holder::$shared['tea'] = 4;
        return [$kept, Holder::$shared];
    },
    'write in a loop' => function () {
        $prices = Map::of(['tea', 3, 'jam', 5]);
        $seen = [];
        foreach ($prices as $name => $price) {
            $prices['bun'] = 1;
            $seen[] = $name;
        }
        return [Set::from($seen), $prices];
    },
];

// Each round runs every row, so the JIT variants compile them before the last round, whose output is printed.
for ($round = 0; $round < 100; $round++) {
    ob_start();
    foreach ($rows as $name => $row) {
        [$original, $changed] = $row();
        echo $name, ': ', json_encode($original), ' ', json_encode($changed), "\n";
    }
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
write in a loop: ["tea","jam"] {"tea":3,"jam":5,"bun":1}
