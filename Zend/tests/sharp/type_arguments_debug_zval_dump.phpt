--TEST--
debug_zval_dump never shows a PHP# object's type arguments
--FILE--
<?php
require __DIR__ . '/harness/Witness.inc';
require __DIR__ . '/harness/Snapshot.inc';
require __DIR__ . '/harness/Sleeper.inc';
require __DIR__ . '/TypeArguments.sharp';

debug_zval_dump(App\Queue::orders());
?>
--EXPECTF--
object(App\PaginatedList)#%d (1) refcount(%d){
  ["items"]=>
  array(0) interned {
  }
}
