--TEST--
A Map or a Set serializes and reads back as itself, alone or inside a List, a struct or a class, in the same process or a fresh one, as a queued job or a cached value does, and tampered data fails with an error
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('Queued');
require __DIR__ . '/table_serialize.inc';

run_rows();
?>
--EXPECT--
a Map<string, int>: Map['tea': 2, 'jam': 5] true
a Map keyed by an enum: Map[Queued\Speed.Express: 1, Queued\Speed.Standard: 3] 1
a Map nested in a List: [Map['tea': 1], Map['jam': 2, 'bun': 3]] true
a struct field: Queued\Shipment(Map['fragile': 1], [3, 4]) true
a class field: Queued\Ledger(Map['packed': 2, 'shipped': 1]) Queued\Ledger(Map['packed': 2])
a Set: Set['vip', 'new'] true true
an empty Map: Map[] 0 true
a Map keyed by a struct: Map[Queued\Parcel(250, Queued\Speed.Express): 'small'] small
a Map keyed by a class that declares ==: Map[Queued\Courier(7): 'north'] north
a Map keyed by a plain PHP object, which has no ==: Map[stdClass(): 'held'] 'held' NULL
the form serialize writes: O:9:"Sharp\Map":4:{i:0;s:3:"tea";i:1;i:2;i:2;s:3:"jam";i:3;i:5;} O:9:"Sharp\Set":2:{i:0;s:3:"vip";i:1;s:3:"new";}
a read-back in a fresh process: 7 values
Map['tea': 2, 'jam': 5] true
Map[Queued\Speed.Express: 1, Queued\Speed.Standard: 3] 1 true
Queued\Shipment(Map['fragile': 1], [3, 4]) true
Queued\Ledger(Map['packed': 2, 'shipped': 1])
Set['vip', 'new'] true
Map[Queued\Parcel(250, Queued\Speed.Express): 'small'] small
Map[Queued\Courier(7): 'north'] north
a bad key count: ValueError: Sharp\Map::__unserialize(): Argument #1 ($data) must be a list of keys each followed by its value
a wrong type, a Set where a Map is declared: TypeError: Cannot assign Sharp.Set to property Queued\Shipment::$labels of type Map<string, int>
