--TEST--
An (array) cast never holds a PHP# object's type arguments
--FILE--
<?php
require __DIR__ . '/harness/Witness.inc';
require __DIR__ . '/harness/Snapshot.inc';
require __DIR__ . '/harness/Sleeper.inc';
require __DIR__ . '/TypeArguments.sharp';

var_dump((array) App\Queue::orders());
?>
--EXPECT--
array(1) {
  ["items"]=>
  array(0) {
  }
}
