--TEST--
A plain PHP closure's `static` result is its called class and its `self` result its class in `is Function<…>`, and a `never` result fits every result type
--FILE--
<?php
require __DIR__ . '/harness/Linked.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('Linking');

var_dump(App\Linking\Report::tests());
?>
--EXPECT--
array(5) {
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
}
