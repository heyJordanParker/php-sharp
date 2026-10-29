--TEST--
A Map's and a Set's isEmpty and isNotEmpty read it, a change to a copy of one leaves the original as it was, a change through a List element or a property with a set body reaches where it lives, a filter keeps its keys and its type, and a member of a value that may be any collection or an object runs the one the value holds
--FILE--
<?php
require __DIR__ . '/table_changes.inc';
?>
--EXPECT--
[true,false,true,false] [false,true,false,true]
[1,2] [2,1,3]
[2,1,2,1,1]
bool(true)
bool(false)
{"tea":1,"milk":3} 3
[true,false,true,false,["b","7"]]
1 2 3 7
bool(true)
bool(false)
bool(true)
