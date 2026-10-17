--TEST--
A preloaded PHP# class calls any expression that gives a function and a static field holding one, gives each loop pass its own captured variable, and runs the value of a collection method, an extension method and a plain PHP function, a collection's on the collection as it was
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/function_values_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
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
16: [1,3,5]
17: false
18: 2
19: false
20: [2]
21: "a"
22: "HEY!"
23: ["A","B"]
24: [1,2]
