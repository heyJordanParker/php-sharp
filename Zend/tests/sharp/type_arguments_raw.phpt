--TEST--
An object of a generic PHP# class that plain PHP creates has its class's bounds as its type arguments
--FILE--
<?php
require __DIR__ . '/harness/Witness.inc';
require __DIR__ . '/harness/Snapshot.inc';
require __DIR__ . '/harness/Sleeper.inc';
require __DIR__ . '/TypeArguments.sharp';

var_dump((new ReflectionObject(new App\PaginatedList([])))->getTypeArguments());
var_dump((new ReflectionObject(new App\Pair(1, 2)))->getTypeArguments());
// The bounds are the type arguments PHP# writes as PaginatedList<DatabaseEntity>, and no others.
var_dump(new App\PaginatedList([]) == App\Queue::entities());
var_dump(new App\PaginatedList([]) == App\Queue::orders());
?>
--EXPECT--
array(1) {
  [0]=>
  string(18) "App.DatabaseEntity"
}
array(2) {
  [0]=>
  string(4) "Any?"
  [1]=>
  string(4) "Any?"
}
bool(true)
bool(false)
