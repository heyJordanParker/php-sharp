--TEST--
A preloaded PHP# class's Map or Set serializes and reads back as itself, as a request with no preloading does
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/table_serialize_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
require __DIR__ . '/table_serialize.inc';

var_dump(class_exists(Queued\Payloads::class, false));
run_rows();
?>
--EXPECT--
bool(true)
a Map<string, int>: Map['tea': 2, 'jam': 5] true
a Map keyed by an enum: Map[Queued\Speed.Express: 1, Queued\Speed.Standard: 3] 1
a Map nested in a List: [Map['tea': 1], Map['jam': 2, 'bun': 3]] true
a struct field: Queued\Shipment(['fragile' => 1], [3, 4]) Sharp\Map array true
a class field: Queued\Ledger(['packed' => 2, 'shipped' => 1]) Sharp\Map Queued\Ledger(['packed' => 2])
a Set: Set['vip', 'new'] true true
an empty Map: Map[] 0 true
a Map keyed by a struct: Map[Queued\Parcel(250, Queued\Speed.Express): 'small'] small
a Map keyed by a class that declares ==: Map[Queued\Courier(7): 'north'] north
a Map keyed by a plain PHP object, which has no ==: Map[stdClass(): 'held'] 'held' NULL
the form serialize writes: O:9:"Sharp\Map":4:{i:0;s:3:"tea";i:1;i:2;i:2;s:3:"jam";i:3;i:5;} O:9:"Sharp\Set":2:{i:0;s:3:"vip";i:1;s:3:"new";}
a read-back in a fresh process: 7 values
Map['tea': 2, 'jam': 5] true
Map[Queued\Speed.Express: 1, Queued\Speed.Standard: 3] 1 true
Queued\Shipment(['fragile' => 1], [3, 4]) Sharp\Map array true
Queued\Ledger(['packed' => 2, 'shipped' => 1]) Sharp\Map
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
allowed_classes => false, which still checks each key's class: [Set['vip', 'new'], Map[__PHP_Incomplete_Class('Queued\\Parcel', 250, Queued\Speed.Express): 'small']]
__unserialize on a Map that holds entries: Error: A Sharp\Map that holds entries is written through its index, which copies a shared one first
a second constructor call on a Set that holds entries: Error: A Sharp\Set that holds entries is written through its index, which copies a shared one first
