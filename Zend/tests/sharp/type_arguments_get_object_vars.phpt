--TEST--
get_object_vars never returns a PHP# object's type arguments
--FILE--
<?php
require __DIR__ . '/harness/Witness.inc';
require __DIR__ . '/harness/Snapshot.inc';
require __DIR__ . '/harness/Sleeper.inc';
require __DIR__ . '/TypeArguments.sharp';

var_dump(get_object_vars(App\Queue::orders()));
?>
--EXPECT--
array(1) {
  ["items"]=>
  array(0) {
  }
}
