--TEST--
PHP# stores a backed enum key in a Map as its value, and plain PHP keeps refusing it
--FILE--
<?php
require __DIR__ . '/enum_keys.inc';
?>
--EXPECT--
array(1) {
  ["active"]=>
  int(2)
}
array(1) {
  ["active"]=>
  int(0)
}
array(1) {
  ["closed"]=>
  int(3)
}
array(1) {
  ["active"]=>
  int(1)
}
array(1) {
  ["closed"]=>
  int(2)
}
array(1) {
  ["active"]=>
  int(0)
}
array(2) {
  [1]=>
  string(5) "small"
  [2]=>
  string(5) "large"
}
array(1) {
  [5]=>
  int(5)
}
int(3)
OutOfRangeException: Undefined array key "closed" in EnumKeys.sharp on line 49
int(3)
NULL
array(1) {
  ["closed"]=>
  int(4)
}
array(1) {
  ["closed"]=>
  array(1) {
    [2]=>
    int(1)
  }
}
array(2) {
  ["Active"]=>
  int(3)
  ["Closed"]=>
  int(4)
}
ValueError: "missing" is not a valid backing value for enum Lib\Standing in EnumKeys.sharp on line 74
array(2) {
  [0]=>
  string(1) "5"
  [1]=>
  string(3) "tea"
}
TypeError: Cannot access offset of type Lib\Standing on array in enum_keys.inc on line 43
TypeError: Cannot access offset of type Lib\Standing on array in enum_keys.inc on line 46
TypeError: Cannot access offset of type Lib\Size on array in enum_keys.inc on line 47
TypeError: Cannot access offset of type Lib\Standing on array in enum_keys.inc on line 48
