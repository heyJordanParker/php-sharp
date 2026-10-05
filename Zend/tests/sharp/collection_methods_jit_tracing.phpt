--TEST--
PHP# collection methods change a List or Map where it lives under the tracing JIT
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.jit=tracing
opcache.jit_buffer_size=32M
opcache.jit_hot_func=1
opcache.jit_hot_loop=1
opcache.jit_hot_return=1
opcache.jit_hot_side_exit=1
--FILE--
<?php
echo 'jit ', opcache_get_status()['jit']['on'] ? 'on' : 'off', "\n";
require __DIR__ . '/collection_methods.inc';
?>
--EXPECT--
jit on
array(3) {
  [0]=>
  int(9)
  [1]=>
  int(2)
  [2]=>
  int(4)
}
array(1) {
  [5]=>
  int(7)
}
int(2)
NULL
int(7)
NULL
array(2) {
  [0]=>
  int(5)
  [1]=>
  int(6)
}
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(2)
}
array(1) {
  [0]=>
  array(2) {
    [0]=>
    int(1)
    [1]=>
    int(2)
  }
}
OutOfRangeException: Undefined array key 2 in CollectionMethods.sharp on line 55
array(1) {
  ["pie"]=>
  int(7)
}
array(1) {
  ["pie"]=>
  int(7)
}
array(3) {
  [0]=>
  int(0)
  [1]=>
  int(1)
  [2]=>
  int(8)
}
array(1) {
  [0]=>
  int(3)
}
array(2) {
  [0]=>
  int(3)
  [1]=>
  int(4)
}
int(2)
int(7)
int(2)
int(2)
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(2)
}
array(2) {
  [0]=>
  int(5)
  [1]=>
  int(6)
}
int(3)
NULL
int(4)
NULL
string(6) "2 of 2"
NULL
DivisionByZeroError: Modulo by zero in CollectionMethods.sharp on line 79
NULL
Error: Call to private Sharp\Collection::__construct() from global scope in collection_methods.inc on line 83
ReflectionException: Class Sharp\Collection is an internal class marked as final that cannot be instantiated without invoking its constructor in collection_methods.inc on line 84
