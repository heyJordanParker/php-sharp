--TEST--
A class value reads its class's constants, its own first and then those it inherits, each with its declared type, its value as a direct read gives it, a Map constant's as the Map a direct read gives, its attributes, and a private one's read refused outside its class
--FILE--
<?php
require __DIR__ . '/harness/Attributes.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('ReflectedConstants');

var_dump(ReflectedConstants\Reads::listed(ReflectedConstants\Limits::class));
var_dump(ReflectedConstants\Reads::listed(ReflectedConstants\Sized::class));
var_dump(ReflectedConstants\Reads::values());
var_dump(ReflectedConstants\Reads::refused());
?>
--EXPECT--
array(3) {
  [0]=>
  string(37) "MAX: int of ReflectedConstants.Limits"
  [1]=>
  string(55) "PER_SIZE: Map<string, int> of ReflectedConstants.Limits"
  [2]=>
  string(41) "CODE: string of ReflectedConstants.Limits"
}
array(3) {
  [0]=>
  string(39) "SMALL: Any? of ReflectedConstants.Sized"
  [1]=>
  string(37) "MAX: int of ReflectedConstants.Limits"
  [2]=>
  string(55) "PER_SIZE: Map<string, int> of ReflectedConstants.Limits"
}
array(7) {
  [0]=>
  int(10)
  [1]=>
  int(5)
  [2]=>
  bool(true)
  [3]=>
  int(1)
  [4]=>
  bool(true)
  [5]=>
  bool(true)
  [6]=>
  string(3) "lim"
}
string(62) "Cannot access private constant ReflectedConstants\Limits::CODE"
