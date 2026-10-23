--TEST--
Plain PHP sorts, references and loops by reference over a PHP# Map or Set property as its PHP array, which PHP# then reads, writes and copies, and gets the array form where it appends to a hooked property, passes a union, iterates an ArrayIterator, reads a lazy Iterable element by element and runs var_export()
--FILE--
<?php
require __DIR__ . '/harness/Arrays.inc';
require __DIR__ . '/harness/Probe.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('Crossings');
require __DIR__ . '/collection_readers.inc';
echo readers_rounds();
?>
--EXPECT--
ksort() of a Map property: Sharp\Map a,b=1,2 true
a reference to a Map property: array / Sharp\Map b,a,k=2,1,1 / 1 / Sharp\Map / {"b":2,"a":1,"k":1,"j":5} / array {"z":26} / array Sharp\Map x=9 / Sharp\Map x,y=9,25 / true
a write of the wrong type through the reference: TypeError: Cannot assign string to reference held by property Demo\Carton::$payload of type Map<string, int> / TypeError: Cannot assign array to reference held by property Demo\Carton::$payload of type Map<string, int>: ["k"] expected int, got string 'x' / {"b":2,"a":1}
a value written through the reference, checked when PHP# reads it: TypeError: Reference held by property Demo\Carton::$payload of type Map<string, int> holds array: ["k"] expected int, got string 'x' / TypeError: Reference held by property Demo\Carton::$payload of type Map<string, int> holds array: ["k"] expected int, got Map
a key the enum lacks, written through the reference, throws on each PHP# read: TypeError: Reference held by property Demo\Carton::$levels of type Map<Demo.Level, int> holds array: ["zzz"] key expected Demo.Level, got string 'zzz' / TypeError: Reference held by property Demo\Carton::$levels of type Map<Demo.Level, int> holds array: ["zzz"] key expected Demo.Level, got string 'zzz'
clone and with after ksort(): Sharp\Map a,b=1,2 / Sharp\Map a,b=1,2
clone and with while plain PHP holds the reference: Sharp\Map b,a=2,1 / {"b":2,"a":1} / Sharp\Map b,a,z=2,1,9 / Sharp\Map b,a,y=2,1,8
a reference to an Any?, an Iterable and a Map<string, Any> property: loose Sharp\Map / shelved Sharp\Map / anyValues Sharp\Map
PHP# index writes after ksort(): 2 2
a PHP# index write while plain PHP holds the reference: array {"a":["x"]}
foreach by reference over a Map property: Sharp\Map b,a=3,2
array_walk() by reference over a Map property: Sharp\Map b,a=20,10
sort() of a Set property: Sharp\Set a,b
a plain PHP parent appends to its PHP# subclass's get => field property: password,secret
get => field over a parent get hook, and get { return field; }: b / token,secret
a PHP array into a Set<string>|int property with a set body: Sharp\Set a
a PHP array into a Set<string>|int and a Map<string, int>|int parameter: Sharp\Set a,b / int 7 / Sharp\Map 1
an ArrayIterator over a PHP# object: payload array {"b":2,"a":1} / codes array ["b","a"] / levels array {"low":1} / mixedKeys array [] / tags array [] / picked int 0 / current() array {"b":2,"a":1} / getArrayCopy() array {"payload":{"b":2,"a":1},"codes":["b","a"],"levels":{"low":1},"mixedKeys":[],"tags":[],"picked":0}
foreach over a lazy Iterable result reads its source in step: read 1, loop 2, read 2, loop 4
iterator_to_array() of a lazy Iterable<Map<string, int>> result: array,array [{"n":1},{"n":2}]
a lazy Iterable result is a Traversable, and its declared type: true iterable iterable
var_export() of a Map property, a Map and a PHP# object: array (   'b' => 2,   'a' => 1, ) / array (   'a' => 1, ) / \Demo\Carton::__set_state(array(    'payload' =>    array (     'b' => 2,     'a' => 1,   ),    'codes' =>    array (     0 => 'b',     1 => 'a',   ),    'levels' =>    array (     'low' => 1,   ),    'mixedKeys' =>    array (   ),    'tags' =>    array (   ),    'picked' => 0, ))
