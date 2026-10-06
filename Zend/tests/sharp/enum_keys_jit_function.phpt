--TEST--
PHP# stores a backed enum key in a Map as its value under the function JIT
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.jit=function
opcache.jit_buffer_size=32M
--FILE--
<?php
echo 'jit ', opcache_get_status()['jit']['on'] ? 'on' : 'off', "\n";
require __DIR__ . '/enum_keys.inc';
?>
--EXPECT--
jit on
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
TypeError: Cannot access offset of type Lib\Status on array in enum_keys.inc on line 38
TypeError: Cannot access offset of type Lib\Status on array in enum_keys.inc on line 41
TypeError: Cannot access offset of type Lib\Size on array in enum_keys.inc on line 42
TypeError: Cannot access offset of type Lib\Status on array in enum_keys.inc on line 43
