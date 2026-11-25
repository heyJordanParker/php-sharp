--TEST--
A List or a Map returned, passed or decoded as an Iterable stays that List or Map, so is List and is Map tell which one it holds, and a loop over it reads a Map's entries
--FILE--
<?php
require __DIR__ . '/effect_classes.inc';
require __DIR__ . '/vendor/autoload.php';

echo implode("\n", Demo\Pick::answers()), "\n";
?>
--EXPECT--
list is List: true
map is Map: true
map is List: false
list loop: 1
map loop: ["a",1]
parameter: a Map: a=1, b=2
decoded is Map: true
