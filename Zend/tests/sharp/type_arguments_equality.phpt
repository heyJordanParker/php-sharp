--TEST--
== is false for two PHP# objects whose type arguments differ, and compares their properties when they match
--FILE--
<?php
require __DIR__ . '/harness/Witness.inc';
require __DIR__ . '/harness/Snapshot.inc';
require __DIR__ . '/harness/Sleeper.inc';
require __DIR__ . '/TypeArguments.sharp';

use App\Queue;

var_dump(Queue::orders() == Queue::entities());
var_dump(Queue::entities() == Queue::orders());
var_dump(Queue::orders() != Queue::entities());
var_dump(Queue::orders() == Queue::orders());
var_dump(Queue::orders() == Queue::orders()->copy());
var_dump(Queue::orders() == new App\PaginatedList([new App\Order(1)]));
?>
--EXPECT--
bool(false)
bool(false)
bool(true)
bool(true)
bool(true)
bool(false)
