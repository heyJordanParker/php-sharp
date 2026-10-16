--TEST--
A PHP# struct is a value: a changed copy leaves the original, == compares by value, a generic one carries its type arguments, and plain PHP can't change or inherit it
--FILE--
<?php
require __DIR__ . '/structs.inc';
?>
--EXPECTF--
array(5) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  string(0) ""
  [3]=>
  string(5) "first"
  [4]=>
  int(1)
}
string(4) "card"
array(5) {
  [0]=>
  bool(true)
  [1]=>
  bool(false)
  [2]=>
  bool(false)
  [3]=>
  bool(false)
  [4]=>
  bool(true)
}
array(6) {
  [0]=>
  bool(true)
  [1]=>
  bool(false)
  [2]=>
  bool(true)
  [3]=>
  bool(false)
  [4]=>
  bool(true)
  [5]=>
  bool(false)
}
array(3) {
  [0]=>
  string(1) "a"
  [1]=>
  int(2)
  [2]=>
  int(1)
}
array(2) {
  [0]=>
  bool(true)
  [1]=>
  bool(false)
}
array(4) {
  [0]=>
  bool(true)
  [1]=>
  bool(false)
  [2]=>
  bool(true)
  [3]=>
  bool(true)
}
Sharp\InvalidFields: first: expected int, got string 'x'; second: expected string, got int 4 in Structs.sharp on line 55
NULL
int(1)
string(23) "int string | string int"
bool(true)
Sharp\InvalidFields: inner.first: expected int, got string 'x' in Structs.sharp on line 50
Error: Cannot modify readonly property Demo\Stamp::$name in structs.inc on line 63
Error: Cannot modify protected(set) property Demo\Stamp::$shown from global scope in structs.inc on line 64
Error: Cannot modify protected(set) readonly property Demo\Stamp::$name from global scope in structs.inc on line 65

Fatal error: Class Sticker cannot extend final class Demo\Stamp in %sstructs.inc(%d) : eval()'d code on line %d
