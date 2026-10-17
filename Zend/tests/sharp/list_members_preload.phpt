--TEST--
A preloaded PHP# class runs a List's flatMap, over an empty List and over Maps' entries too, and toList on a Set, on a Map, on a Map read through an index and on a Map's values
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
echo json_encode(Shelf::entries([new Sharp\Map(['a', 1, 'b', 2]), new Sharp\Map(['c', 3])])), "\n";
echo json_encode(Shelf::fromSet(['b', 'a', 'c'])), "\n";
echo json_encode(Shelf::fromValues(['tea' => 3, 'pie' => 7])), "\n";
echo json_encode(Shelf::fromMap(new Sharp\Map(['tea', 3, 'pie', 7]))), "\n";
echo json_encode(Shelf::fromFirstMap([new Sharp\Map(['tea', 3])])), "\n";
echo json_encode(Shelf::copied([4, 5])), "\n";
?>
--EXPECT--
bool(true)
[1,2,3]
[]
[1,1,2,2,3,3]
[["a",1],["b",2],["c",3]]
["b","a","c"]
[3,7]
[["tea",3],["pie",7]]
[["tea",3]]
[2,3]
