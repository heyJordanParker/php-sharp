--TEST--
A List's removeLast, removeFirst and removeAt give the element they remove and change the List where it lives, a property with a set body through its set, isEmpty and isNotEmpty read it, and entries through a member's value makes a Map of the element type
--FILE--
<?php
require __DIR__ . '/list_changes.inc';
?>
--EXPECT--
c ["a","b"]
b ["a"]
y ["x"] 2
old ["new"]
["b","a","c"]
["b","c"]
only
[true,false] [false,true]
NULL
int(1)
[true,false,"b"]
OutOfRangeException: The list is empty.
OutOfRangeException: The list is empty.
OutOfRangeException: Undefined array key 3
OutOfRangeException: The list is empty.
