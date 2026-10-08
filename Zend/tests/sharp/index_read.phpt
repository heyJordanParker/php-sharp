--TEST--
A bare PHP# index read throws OutOfRangeException on a missing key, ?? and ?. read it as null, and an object key throws PHP's TypeError
--FILE--
<?php
require __DIR__ . '/index_read.inc';
?>
--EXPECT--
int(20)
OutOfRangeException: Undefined array key 2 in IndexRead.sharp on line 9
OutOfRangeException: Undefined array key -1 in IndexRead.sharp on line 9
int(2)
OutOfRangeException: Undefined array key 2 in IndexRead.sharp on line 14
OutOfRangeException: Undefined array key 1 in IndexRead.sharp on line 14
int(0)
int(5)
int(0)
OutOfRangeException: Undefined array key 2 in IndexRead.sharp on line 30
bool(false)
OutOfRangeException: Undefined array key 2 in IndexRead.sharp on line 36
int(4)
NULL
int(3)
OutOfRangeException: Undefined array key "closed" in IndexRead.sharp on line 46
TypeError: Cannot access offset of type Lib\Standing on array in IndexRead.sharp on line 46
NULL
string(37) "Trying to access array offset on null"
