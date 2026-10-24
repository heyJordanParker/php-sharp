--TEST--
Plain PHP reaches each PHP# Map and Set of collection_readers as its PHP array, and each lazy Iterable as a Traversable, under the tracing JIT
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.jit=tracing
opcache.jit_buffer_size=32M
opcache.jit_hot_func=1
opcache.jit_hot_loop=1
opcache.jit_hot_return=1
opcache.jit_hot_side_exit=1
--SKIPIF--
<?php
if (ini_get('opcache.jit') === false) die('skip JIT is not available');
?>
--FILE--
<?php
echo 'jit ', opcache_get_status()['jit']['on'] ? 'on' : 'off', "\n";
require __DIR__ . '/harness/Arrays.inc';
require __DIR__ . '/harness/Probe.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('Crossings');
require __DIR__ . '/collection_readers.inc';
echo readers_rounds();
?>
--EXPECT--
jit on
ksort() of a Map property: Sharp\Map a,b=1,2 true
a reference to a Map property: array / Sharp\Map b,a,k=2,1,1 / 1 / Sharp\Map / {"b":2,"a":1,"k":1,"j":5} / array {"z":26} / array Sharp\Map x=9 / Sharp\Map x,y=9,25 / true
a write of the wrong type through the reference: TypeError: Cannot assign string to reference held by property Demo\Carton::$payload of type Map<string, int> / TypeError: Cannot assign array to reference held by property Demo\Carton::$payload of type Map<string, int>: ["k"] expected int, got string 'x' / {"b":2,"a":1}
a value written through the reference, checked when PHP# reads it: TypeError: Reference held by property Demo\Carton::$payload of type Map<string, int> holds array: ["k"] expected int, got string 'x' / TypeError: Reference held by property Demo\Carton::$payload of type Map<string, int> holds array: ["k"] expected int, got Map
a key the enum lacks, written through the reference, throws on each PHP# read: TypeError: Reference held by property Demo\Carton::$levels of type Map<Demo.Level, int> holds array: ["zzz"] key expected Demo.Level, got string 'zzz' / TypeError: Reference held by property Demo\Carton::$levels of type Map<Demo.Level, int> holds array: ["zzz"] key expected Demo.Level, got string 'zzz'
clone and with after ksort(): Sharp\Map a,b=1,2 / Sharp\Map a,b=1,2
clone and with while plain PHP holds the reference: Sharp\Map b,a,z=2,1,9 / {"b":2,"a":1,"z":9} / Sharp\Map b,a,z=2,1,9 / Sharp\Map b,a,z,y=2,1,9,8
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
an ArrayIterator over a PHP# object: payload array {"b":2,"a":1} / codes array ["b","a"] / levels array {"low":1} / mixedKeys array [] / tags array [] / counts array [1,2] / grid array [[1]] / numbers array [1] / tallies array {"top":9223372036854775807,"low":1} / shelves array {"k":["a"]} / nested array {"k":{"n":1}} / marks bool false / later array [] / picked int 0 / current() array {"b":2,"a":1} / getArrayCopy() array {"payload":{"b":2,"a":1},"codes":["b","a"],"levels":{"low":1},"mixedKeys":[],"tags":[],"counts":[1,2],"grid":[[1]],"numbers":[1],"tallies":{"top":9223372036854775807,"low":1},"shelves":{"k":["a"]},"nested":{"k":{"n":1}},"marks":false,"later":[],"picked":0}
foreach over a lazy Iterable result reads its source in step: read 1, loop 2, read 2, loop 4
iterator_to_array() of a lazy Iterable<Map<string, int>> result: array,array [{"n":1},{"n":2}]
a lazy Iterable result is a Traversable, and its declared type: true iterable iterable
var_export() of a Map property, a Map and a PHP# object: array (   'b' => 2,   'a' => 1, ) / array (   'a' => 1, ) / \Demo\Carton::__set_state(array(    'payload' =>    array (     'b' => 2,     'a' => 1,   ),    'codes' =>    array (     0 => 'b',     1 => 'a',   ),    'levels' =>    array (     'low' => 1,   ),    'mixedKeys' =>    array (   ),    'tags' =>    array (   ),    'counts' =>    array (     0 => 1,     1 => 2,   ),    'grid' =>    array (     0 =>      array (       0 => 1,     ),   ),    'numbers' =>    array (     0 => 1,   ),    'tallies' =>    array (     'top' => 9223372036854775807,     'low' => 1,   ),    'shelves' =>    array (     'k' =>      array (       0 => 'a',     ),   ),    'nested' =>    array (     'k' =>      array (       'n' => 1,     ),   ),    'marks' => false,    'later' =>    array (   ),    'picked' => 0, ))
plain PHP's writes into a List<int> property, checked when PHP# reads it: append TypeError: Reference held by property Demo\Carton::$counts of type List<int> holds array: [2] expected int, got string 'z' / index write TypeError: Reference held by property Demo\Carton::$counts of type List<int> holds array: [1] expected int, got string 'z' / nested write TypeError: Reference held by property Demo\Carton::$grid of type List<List<int>> holds array: [0][1] expected int, got string 'z' / reference write TypeError: Reference held by property Demo\Carton::$counts of type List<int> holds array: [2] expected int, got string 'z' / array_push() TypeError: Reference held by property Demo\Carton::$counts of type List<int> holds array: [2] expected int, got string 'z' / foreach by reference TypeError: Reference held by property Demo\Carton::$counts of type List<int> holds array: [0] expected int, got string 'z'
plain PHP's writes that fit a List<int> property, and a reference it keeps: 2,3,5 1 / 2,3,5,7 / 2,3,5,7,8,9 [2,3,5,7,8,9] / TypeError: Reference held by property Demo\Carton::$counts of type List<int> holds array: [6] expected int, got string 'z'
plain PHP's writes into a static, an uninitialized and a type parameter's List<int>: static TypeError: Reference held by property Demo\Carton::$totals of type List<int> holds array: [2] expected int, got string 'z' / static that fits 6 / uninitialized TypeError: Reference held by property Demo\Carton::$later of type List<int> holds array: [0] expected int, got string 'z' / type parameter TypeError: Reference held by property Demo\Held::$value of type List<int> holds array: [1] expected int, got string 'z'
plain PHP's writes into a Map or a Set property, checked at the write: a string value into a Map<string, int> TypeError: Cannot assign string to Map<string, int>: ["k"] expected int, got string 'x' / a key no case backs into a Map<Demo.Level, int> TypeError: Cannot use string 'zzz' as a key of Map<Demo.Level, int>: expected Demo.Level / a string element into a Set<int> TypeError: Cannot assign string to Set<int>: [1] expected int, got string 'x' / a string value into a static Map<string, int> TypeError: Cannot assign string to Map<string, int>: ["k"] expected int, got string 'x' / a string into a nested Map<string, int> TypeError: Cannot assign string to Map<string, int>: ["m"] expected int, got string 'x' / writes that fit b,a,5=2,1,5 2 2 1,2 a=1
a Map or a Set PHP# code makes, typed from when it is made: a literal TypeError: Cannot assign string to Map<string, int>: ["k"] expected int, got string 'x' / groupBy() TypeError: Cannot assign int to Map<string, List<int>>: ["k"] expected List<int>, got int 5 / associateBy() TypeError: Cannot assign string to Map<string, int>: ["k"] expected int, got string 'x' / entries() TypeError: Cannot use string 'k' as a key of Map<int, int>: expected int / toSet() TypeError: Cannot assign string to Set<int>: [2] expected int, got string 'x' / writes that fit no TypeError
a Map whose type names a class or a type parameter, typed from when it is made: an initial value of a Map<string, T> TypeError: Cannot assign string to Map<string, int>: ["k"] expected int, got string 'x' / a static of a Map<string, T> TypeError: Cannot assign string to Map<string, int>: ["k"] expected int, got string 'x' / a Map<Demo.Level, Demo.Carton> parameter TypeError: Cannot assign int to Map<Demo.Level, Demo.Carton>: ["high"] expected Demo.Carton, got int 5 / a Map<string, Demo.Carton> result TypeError: Cannot assign int to Map<string, Demo.Carton>: ["k"] expected Demo.Carton, got int 5 / a Map<string, Demo.Carton> local TypeError: Cannot assign int to Map<string, Demo.Carton>: ["k"] expected Demo.Carton, got int 5 / entries() as a method value in a generic method TypeError: Cannot use string 'k' as a key of Map<int, string>: expected int / writes that fit no TypeError
plain PHP's writes into a Map's values, checked when PHP# reads it: an append to a missing int entry TypeError: Map<string, int> holds array under the key string 'new' / a decrement of a missing entry TypeError: Map<string, int> holds null under the key string 'missing' / an increment past PHP_INT_MAX TypeError: Map<string, int> holds float under the key string 'top' / an int into a Set<string> value TypeError: Map<string, Set<string>> holds array under the key string 'a': [0] expected string, got int 5 / an int into a List<string> value TypeError: Map<string, List<string>> holds array under the key string 'k': [1] expected string, got int 5
plain PHP's writes that fit a Map's values: a,b / x / 1,2 / top,low=9223372036854775807,2 / Sharp\Map b,a=2,11
PHP#'s += and ++ after plain PHP's write: += 1 TypeError: Reference held by property Demo\Carton::$counts of type List<int> holds array: [1] expected int, got float 1.5 / += 1 that fits 5 / ++ TypeError: Reference held by property Demo\Carton::$counts of type List<int> holds array: [1] expected int, got float 1.5 / ++ that fits 5 / static += 1 TypeError: Reference held by property Demo\Carton::$totals of type List<int> holds array: [1] expected int, got float 1.5 / static += 1 that fits 3
plain PHP's readers and clone after a wrong write: var_dump holds "z" / serialize holds "z" / (array) [1,2,"z"] / get_object_vars [1,2,"z"] / clone [1,2,"z"] TypeError: Reference held by property Demo\Carton::$counts of type List<int> holds array: [2] expected int, got string 'z'
an element write into a property that holds false: TypeError: Reference held by property Demo\Carton::$marks of type List<int>|bool holds array: [0] expected int, got string 'z' / 3
