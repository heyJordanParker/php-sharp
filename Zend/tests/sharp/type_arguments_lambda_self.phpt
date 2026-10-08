--TEST--
new Self in a lambda of a generic PHP# class takes this's type arguments, as new Self in a method does
--FILE--
<?php
require __DIR__ . '/harness/Witness.inc';
require __DIR__ . '/harness/Snapshot.inc';
require __DIR__ . '/harness/Sleeper.inc';
require __DIR__ . '/TypeArguments.sharp';

$copy = App\Queue::orders()->copyLater();
var_dump($copy::class, (new ReflectionObject($copy))->getTypeArguments());
?>
--EXPECT--
string(17) "App\PaginatedList"
array(1) {
  [0]=>
  string(9) "App.Order"
}
