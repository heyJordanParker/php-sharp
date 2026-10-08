--TEST--
clone copies a PHP# object's type arguments
--FILE--
<?php
require __DIR__ . '/harness/Witness.inc';
require __DIR__ . '/harness/Snapshot.inc';
require __DIR__ . '/harness/Sleeper.inc';
require __DIR__ . '/TypeArguments.sharp';

$pair = App\Queue::pair();
$copy = clone $pair;
unset($pair);
var_dump((new ReflectionObject($copy))->getTypeArguments());
var_dump((new ReflectionObject(clone new App\PaginatedList([])))->getTypeArguments());
?>
--EXPECT--
array(2) {
  [0]=>
  string(9) "App.Order"
  [1]=>
  string(3) "int"
}
array(1) {
  [0]=>
  string(18) "App.DatabaseEntity"
}
