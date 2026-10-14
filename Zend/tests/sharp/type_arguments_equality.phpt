--TEST--
== is false and <=> gives 1 both ways for two PHP# objects whose type arguments differ, as for objects of different classes, and both compare properties when the type arguments match
--FILE--
<?php
require __DIR__ . '/type_arguments.inc';

use App\Queue;

echo "==\n";
var_dump(Queue::orders() == Queue::entities());
var_dump(Queue::entities() == Queue::orders());
var_dump(Queue::orders() != Queue::entities());
var_dump(Queue::orders() == Queue::orders());
var_dump(Queue::orders() == Queue::orders()->copy());
var_dump(Queue::orders() == new App\PaginatedList([new App\Order(1)]));

echo "<=>\n";
var_dump(Queue::orders() <=> Queue::entities());
var_dump(Queue::entities() <=> Queue::orders());
var_dump(Queue::orders() < Queue::entities(), Queue::orders() > Queue::entities());
var_dump(Queue::orders() <=> Queue::orders());
// Objects of different classes compare the same way.
var_dump(new App\Order(1) <=> new stdClass());

echo "a type unserialize reads before code spells it\n";
$read = unserialize(str_replace('s:10:"Any?, Any?"', 's:11:"int, string"', serialize(new App\Pair(1, 'one'))));
require __DIR__ . '/TypeArgumentsAlias.sharp';
var_dump(Aliased\Maker::pair() == $read);
?>
--EXPECT--
==
bool(false)
bool(false)
bool(true)
bool(true)
bool(true)
bool(false)
<=>
int(1)
int(1)
bool(false)
bool(false)
int(0)
int(1)
a type unserialize reads before code spells it
bool(true)
