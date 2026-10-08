--TEST--
<=> gives 1 both ways for two PHP# objects whose type arguments differ, as for objects of different classes
--FILE--
<?php
require __DIR__ . '/harness/Witness.inc';
require __DIR__ . '/harness/Snapshot.inc';
require __DIR__ . '/harness/Sleeper.inc';
require __DIR__ . '/TypeArguments.sharp';

use App\Queue;

var_dump(Queue::orders() <=> Queue::entities());
var_dump(Queue::entities() <=> Queue::orders());
var_dump(Queue::orders() < Queue::entities(), Queue::orders() > Queue::entities());
var_dump(Queue::orders() <=> Queue::orders());
// Objects of different classes compare the same way.
var_dump(new App\Order(1) <=> new stdClass());
?>
--EXPECT--
int(1)
int(1)
bool(false)
bool(false)
int(0)
int(1)
