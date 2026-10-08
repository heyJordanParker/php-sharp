--TEST--
var_export never shows a PHP# object's type arguments
--FILE--
<?php
require __DIR__ . '/harness/Witness.inc';
require __DIR__ . '/harness/Snapshot.inc';
require __DIR__ . '/harness/Sleeper.inc';
require __DIR__ . '/TypeArguments.sharp';

var_export(App\Queue::orders());
?>
--EXPECT--
\App\PaginatedList::__set_state(array(
   'items' => 
  array (
  ),
))
