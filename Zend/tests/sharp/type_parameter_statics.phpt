--TEST--
PHP# TItem.make() and TItem.size reach the statics of the type argument the code runs with, which it redeclares or inherits, and a type argument of two classes is no class to reach them on
--FILE--
<?php

require __DIR__ . '/harness/fixture.inc';
fixture('TypeStatics');

var_dump(TypeStatics\Builds::ofClasses());
try {
    TypeStatics\Builds::ofUnion();
} catch (Error $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
?>
--EXPECT--
array(9) {
  [0]=>
  string(5) "fancy"
  [1]=>
  string(10) "fancy size"
  [2]=>
  string(5) "maker"
  [3]=>
  string(10) "maker size"
  [4]=>
  string(5) "maker"
  [5]=>
  string(5) "fancy"
  [6]=>
  string(10) "fancy size"
  [7]=>
  string(5) "maker"
  [8]=>
  string(10) "maker size"
}
Error: Class "TypeStatics.Fancy|TypeStatics.Plain" not found
