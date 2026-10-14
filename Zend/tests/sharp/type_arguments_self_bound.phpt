--TEST--
An object plain PHP creates of a class whose bound names the class's own type parameter holds that bound with Any? in its place, and serialize keeps it
--FILE--
<?php
require __DIR__ . '/Sorted.sharp';

$sorted = new Demo\Sorted();
var_dump((new ReflectionObject($sorted))->getTypeArguments());
var_dump((new ReflectionObject(unserialize(serialize($sorted))))->getTypeArguments());
?>
--EXPECT--
array(1) {
  [0]=>
  string(21) "Demo.Comparable<Any?>"
}
array(1) {
  [0]=>
  string(21) "Demo.Comparable<Any?>"
}
