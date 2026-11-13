--TEST--
A plain PHP closure's `static` result is its called class and its `self` result its class in `is Function<…>`, and a `never` result fits every result type, a plain `fn (): never` and a PHP# lambda that always throws alike
--FILE--
<?php
require __DIR__ . '/harness/Linked.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('Linking');

var_dump(App\Linking\Report::tests());
?>
--EXPECT--
array(7) {
  [0]=>
  bool(true)
  [1]=>
  bool(true)
  [2]=>
  bool(false)
  [3]=>
  bool(true)
  [4]=>
  bool(true)
  [5]=>
  bool(true)
  [6]=>
  bool(true)
}
