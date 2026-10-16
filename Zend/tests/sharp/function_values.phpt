--TEST--
PHP# calls any expression that gives a function and a static field holding one, and gives each loop pass its own captured variable
--FILE--
<?php
require __DIR__ . '/function_values.inc';

foreach (function_values() as $index => $row) {
    echo $index, ': ', json_encode($row), "\n";
}
?>
--EXPECT--
0: 6
1: 10
2: -5
3: null
4: 20
5: 10
6: 25
7: 5
8: 15
9: 4
10: 15
11: 21
12: "hi!"
13: [10,20,30]
14: [0,2,3]
15: [1,3,5]
