--TEST--
A Map or a Set serializes and reads back as itself, alone or inside a List, a struct or a class, in the same process or a fresh one, as a queued job or a cached value does, and tampered data fails with an error
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('Queued');
require __DIR__ . '/../../../sharp/composer/library/Sharp/Data/PhpSerializer.sharp';
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
a bad key count: ValueError: A serialized Map must hold each key followed by its value
a Set under named keys: ValueError: A serialized Set must hold a list of its elements
a wrong type, a Set where a Map is declared: TypeError: Cannot assign Sharp.Set to property Queued\Shipment::$labels of type Map<string, int>
a wrong value in a class field: TypeError: Cannot assign Sharp.Map to property Queued\Ledger::$totals of type Map<string, int>: ["packed"] expected int, got string 'two'
a wrong key in a struct field: TypeError: Cannot assign Sharp.Map to property Queued\Shipment::$labels of type Map<string, int>: [9] key expected string, got int 9
a wrong element in a Set field: TypeError: Cannot assign Sharp.Set to property Queued\Crate::$speeds of type Set<Queued.Speed>: [0] expected Queued.Speed, got int 5
a wrong value inside a List<Map<string, int>> field: TypeError: Cannot assign array to property Queued\Crate::$batches of type List<Map<string, int>>: [0]["tea"] expected int, got string 'x'
a generic class field: TypeError: Cannot assign Sharp.Map to property Queued\Box::$held of type Map<string, int>: ["tea"] expected int, got string 'x'
Sharp.Data.PhpSerializer, which allows no class by default: Map['tea': 2, 'jam': 5]
PhpSerializer allowing a class by its PHP# name: Queued\Crate(Map[3: 'c'], Set[Queued\Speed.Express], [Map['tea': 1]])
allowed_classes => false, which still checks each key's class: [Set['vip', 'new'], Map[__PHP_Incomplete_Class('Queued\\Parcel', 250, Queued\Speed.Express): 'small']]
__unserialize on a Map that holds entries: Error: A Sharp\Map that holds entries is written through its index, which copies a shared one first
a second constructor call on a Set that holds entries: Error: A Sharp\Set that holds entries is written through its index, which copies a shared one first
