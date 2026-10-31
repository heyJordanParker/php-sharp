--TEST--
A Map's and a Set's isEmpty and isNotEmpty read it, a change to a copy of one leaves the original as it was, a change through a List element or a property with a set body reaches where it lives, a filter keeps its keys and its type, and a member of a value that may be any collection or an object runs the one the value holds, makes a Map or a Set of that kind's type, and changes a property plain PHP holds a reference to
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
[true,false,true,false]
["open=1",{"open":1},5]
1 3 1 2 3
[1,2]
Cannot assign string to Set<int>: [2] expected int, got string 'x'
{"1":[1,3],"0":[2]} {"10":1,"20":2,"30":3}
Cannot assign string to Map<int, List<int>>: [7] expected List<int>, got string 'x'
Cannot assign string to Map<int, int>: [7] expected int, got string 'x'
{"1":[1],"0":[2]} {"10":1,"20":2}
Cannot assign string to Map<int, List<int>>: [7] expected List<int>, got string 'x'
Cannot assign string to Map<int, int>: [7] expected int, got string 'x'
bool(true)
bool(true)
[1,5] [1,5] {"milk":1} {"milk":1}
55 55 [5,7] ["first","second"]
{"z":1,"a":1}
bool(true)
bool(false)
2 ["vip0","vip1","vip2"] 6
