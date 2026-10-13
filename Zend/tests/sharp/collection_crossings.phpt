--TEST--
A Map or a Set crosses into a plain PHP parameter that names array as a PHP array, and a PHP array crosses back into a Map
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
    $output = ob_get_clean();
}
echo $output;
?>
--EXPECT--
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
