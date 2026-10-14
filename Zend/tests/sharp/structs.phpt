--TEST--
A PHP# struct is a value: a changed copy leaves the original, == compares by value, and plain PHP can't change or inherit it
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
Error: Cannot modify readonly property Demo\Stamp::$name in structs.inc on line 44
Error: Cannot modify protected(set) property Demo\Stamp::$shown from global scope in structs.inc on line 45
Error: Cannot modify protected(set) readonly property Demo\Stamp::$name from global scope in structs.inc on line 46

Fatal error: Class Sticker cannot extend final class Demo\Stamp in %sstructs.inc(%d) : eval()'d code on line %d
