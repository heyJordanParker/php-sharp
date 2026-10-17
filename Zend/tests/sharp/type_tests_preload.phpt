--TEST--
A preloaded PHP# `is`, `as` and `match` test a tuple's count and element types, a class value's class and an object, alone or in a union
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/type_tests_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(TypeTests\TypeTests::class, false));
foreach (TypeTests\TypeTests::tested() as $index => $result) {
    echo $index, ': ', var_export($result, true), "\n";
}
?>
--EXPECT--
bool(true)
0: true
1: false
2: false
3: true
4: true
5: true
6: true
7: true
8: true
9: true
10: false
11: true
12: false
13: false
14: false
15: true
16: true
17: true
18: true
19: true
20: true
21: true
22: false
23: true
