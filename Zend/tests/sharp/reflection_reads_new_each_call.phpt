--TEST--
PHP# attributes<T>() makes a new object of each attribute on every read, and enumCases() gives equal lists of the same names, cases and backing values on every read, a pure enum's case with none
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

var_dump(ReflectionReads\Reads::distinctAttributes());
var_dump(ReflectionReads\Reads::shades());
var_dump(ReflectionReads\Reads::tones());
?>
--EXPECT--
bool(true)
array(2) {
  [0]=>
  string(23) "Light light Light equal"
  [1]=>
  string(20) "Dark dark Dark equal"
}
array(2) {
  [0]=>
  string(9) "Warm none"
  [1]=>
  string(9) "Cool none"
}
