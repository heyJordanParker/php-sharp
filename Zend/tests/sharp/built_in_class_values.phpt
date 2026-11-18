--TEST--
A class value of a built-in generic answers is as a user generic's does: List<string> is a Class<List>, a Class<List<Any?>> and a Class<Iterable<Any?>>, a Map's and a Set's are of their raw class and of Iterable, and none is of another collection or a type argument its own isn't within
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

var_dump(BuiltInClassValues\Tests::answers());
?>
--EXPECT--
array(11) {
  [0]=>
  bool(true)
  [1]=>
  bool(true)
  [2]=>
  bool(true)
  [3]=>
  bool(true)
  [4]=>
  bool(true)
  [5]=>
  bool(true)
  [6]=>
  bool(true)
  [7]=>
  bool(true)
  [8]=>
  bool(true)
  [9]=>
  bool(false)
  [10]=>
  bool(false)
}
