--TEST--
PHP# stores a backed enum key in a Map as its value under the function JIT
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.jit=function
opcache.jit_buffer_size=32M
--SKIPIF--
<?php
if (ini_get('opcache.jit') === false) die('skip JIT is not available');
?>
--FILE--
<?php
echo 'jit ', opcache_get_status()['jit']['on'] ? 'on' : 'off', "\n";
require __DIR__ . '/enum_keys.inc';
?>
--EXPECTF--
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
TypeError: Demo\Headcount::byName(): Argument #1 ($counts) must be of type Map<Lib.Standing, int>, array given: ["missing"] key expected Lib.Standing, got string 'missing', called in %s on line %d in Headcount.sharp on line 67
array(2) {
  [0]=>
  string(1) "5"
  [1]=>
  string(3) "tea"
}
array(2) {
  ["active"]=>
  array(1) {
    [0]=>
    int(1)
  }
  ["closed"]=>
  array(2) {
    [0]=>
    int(2)
    [1]=>
    int(3)
  }
}
array(2) {
  [0]=>
  string(6) "Active"
  [1]=>
  string(6) "Closed"
}
TypeError: Cannot access offset of type Lib\Standing on array in enum_keys.inc on line 45
TypeError: Cannot access offset of type Lib\Standing on array in enum_keys.inc on line 48
TypeError: Cannot access offset of type Lib\Size on array in enum_keys.inc on line 49
TypeError: Cannot access offset of type Lib\Standing on array in enum_keys.inc on line 50
