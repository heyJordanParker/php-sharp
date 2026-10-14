--TEST--
A Map keeps its entries in insertion order through growth, removal and re-insertion
--FILE--
<?php
use Sharp\Map;
use Sharp\Set;

function keys(Map|Set $table): string
{
    $keys = [];
    foreach ($table as $key => $value) {
        $keys[] = $table instanceof Set ? $value : $key;
    }
    return implode(',', $keys);
}

$rows = [
    'a replaced value keeps its place' => function () {
        $map = new Map(['a', 1, 'b', 2, 'c', 3]);
        $map['a'] = 9;
        return keys($map) . ' ' . $map['a'];
    },
    'a removed key comes back last' => function () {
        $map = new Map(['a', 1, 'b', 2, 'c', 3]);
        unset($map['a']);
        $map['a'] = 4;
        return keys($map);
    },
    'removing the last keys' => function () {
        $map = new Map(['a', 1, 'b', 2, 'c', 3]);
        unset($map['c'], $map['b']);
        $map['d'] = 4;
        return keys($map) . ' ' . count($map);
    },
    'removing a missing key' => function () {
        $map = new Map(['a', 1]);
        unset($map['z']);
        return keys($map) . ' ' . count($map);
    },
    'growth past the first capacity' => function () {
        $map = new Map();
        for ($n = 0; $n < 1000; $n++) {
            $map[$n] = $n * 2;
        }
        return count($map) . ' ' . $map[0] . ' ' . $map[999] . ' ' . var_export($map[1000], true);
    },
    'removal down to a few keys' => function () {
        $map = new Map();
        for ($n = 0; $n < 1000; $n++) {
            $map[$n] = $n;
        }
        for ($n = 0; $n < 997; $n++) {
            unset($map[$n]);
        }
        $map[0] = 0;
        return keys($map) . ' ' . $map[998];
    },
    // A Map used as a queue reuses its removed slots, so it stays at its first capacity of 8 entries.
    'reuse of removed slots' => function () {
        $map = new Map();
        $before = memory_get_usage();
        for ($round = 0; $round < 50; $round++) {
            for ($n = 0; $n < 7; $n++) {
                $map[$round * 7 + $n] = $n;
            }
            for ($n = 0; $n < 7; $n++) {
                unset($map[$round * 7 + $n]);
            }
        }
        $map['last'] = 1;
        return keys($map) . ' ' . count($map) . ' ' . (memory_get_usage() - $before < 1024 ? 'small' : 'grew');
    },
    'a Set keeps its first order' => fn () => keys(new Set([3, 1, 3, 2, 1])),
];

// Each round runs every row, so the JIT variants compile them before the last round, whose output is printed.
for ($round = 0; $round < 100; $round++) {
    ob_start();
    foreach ($rows as $name => $row) {
        echo $name, ': ', $row(), "\n";
    }
    $output = ob_get_clean();
}
echo $output;
?>
--EXPECT--
a replaced value keeps its place: a,b,c 9
a removed key comes back last: b,c,a
removing the last keys: a,d 2
removing a missing key: a 1
growth past the first capacity: 1000 0 1998 NULL
removal down to a few keys: 997,998,999,0 998
reuse of removed slots: last 1 small
a Set keeps its first order: 3,1,2
