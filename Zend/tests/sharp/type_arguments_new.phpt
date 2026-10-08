--TEST--
new of a generic PHP# class gives the object its type arguments, which plain PHP reads through ReflectionObject
--FILE--
<?php
require __DIR__ . '/harness/Witness.inc';
require __DIR__ . '/harness/Snapshot.inc';
require __DIR__ . '/harness/Sleeper.inc';
require __DIR__ . '/TypeArguments.sharp';

use App\Queue;

function arguments(object $object): array
{
    return (new ReflectionObject($object))->getTypeArguments();
}

var_dump(arguments(Queue::orders()));
var_dump(arguments(Queue::pair()));
// new Self gives the new object the type arguments of this.
var_dump(arguments(Queue::orders()->copy()));
var_dump(arguments(Queue::entities()->copy()));
// OrderPage and Order declare no type parameter.
var_dump(arguments(Queue::page()));
var_dump(arguments(new App\Order(1)));
var_dump(arguments(new stdClass()));
?>
--EXPECT--
array(1) {
  [0]=>
  string(9) "App.Order"
}
array(2) {
  [0]=>
  string(9) "App.Order"
  [1]=>
  string(3) "int"
}
array(1) {
  [0]=>
  string(9) "App.Order"
}
array(1) {
  [0]=>
  string(18) "App.DatabaseEntity"
}
array(0) {
}
array(0) {
}
array(0) {
}
