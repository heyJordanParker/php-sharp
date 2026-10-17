--TEST--
A preloaded PHP# class runs a List's flatMap, over an empty List too, and toList on a Set and on a Map's values
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/list_members_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(ListMembers\Shelf::class, false));

use ListMembers\Shelf;

echo json_encode(Shelf::flat([[1, 2], [], [3]])), "\n";
echo json_encode(Shelf::flat([])), "\n";
echo json_encode(Shelf::doubled([[1, 2], [3]])), "\n";
echo json_encode(Shelf::fromSet(['b', 'a', 'c'])), "\n";
echo json_encode(Shelf::fromValues(['tea' => 3, 'pie' => 7])), "\n";
echo json_encode(Shelf::copied([4, 5])), "\n";
?>
--EXPECT--
bool(true)
[1,2,3]
[]
[1,1,2,2,3,3]
["b","a","c"]
[3,7]
[2,3]
