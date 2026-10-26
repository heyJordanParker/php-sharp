--TEST--
A List's removeLast, removeFirst and removeAt give the element they remove and change the List where it lives, a property with a set body through its set, isEmpty and isNotEmpty read it, and entries through a member's value makes a Map of the element type, a List<int> property stays checked after repeated changes, a change through an element, one that threw too, leaves nothing a copy of the List shares, and a local plain PHP holds a reference to keeps it after a change
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
[1,7,9,7]
[true,false] [false,true]
NULL
int(1)
[true,false,"b"]
[[1]] [[1]]
[[1,2]] [[1,2]]
[1,2,3]
TypeError: Reference held by property ListChanges\Ledger::$counts of type List<int> holds array: [3] expected int, got string 'z'
[1,2,3]
OutOfRangeException: The list is empty.
OutOfRangeException: The list is empty.
OutOfRangeException: Undefined array key 3
OutOfRangeException: The list is empty.
