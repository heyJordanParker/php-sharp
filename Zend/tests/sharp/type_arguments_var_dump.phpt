--TEST--
var_dump never shows a PHP# object's type arguments
--FILE--
<?php
require __DIR__ . '/harness/Witness.inc';
require __DIR__ . '/harness/Snapshot.inc';
require __DIR__ . '/harness/Sleeper.inc';
require __DIR__ . '/TypeArguments.sharp';

var_dump(App\Queue::orders());
var_dump(new App\PaginatedList([]));
?>
--EXPECTF--
object(App\PaginatedList)#%d (1) {
  ["items"]=>
  array(0) {
  }
}
object(App\PaginatedList)#%d (1) {
  ["items"]=>
  array(0) {
  }
}
