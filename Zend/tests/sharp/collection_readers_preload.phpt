--TEST--
Plain PHP preloaded with PHP# classes reaches each Map and Set of collection_readers as its PHP array, and each lazy Iterable as a Traversable, though the optimizer knows every class and property it reads
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/collection_readers_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(function_exists('readers_rounds'), class_exists(Demo\Carton::class, false));
echo readers_rounds();
?>
--EXPECT--
bool(true)
bool(true)
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
an ArrayIterator over a PHP# object: payload array {"b":2,"a":1} / codes array ["b","a"] / levels array {"low":1} / mixedKeys array [] / tags array [] / counts array [1,2] / grid array [[1]] / numbers array [1] / later array [] / picked int 0 / current() array {"b":2,"a":1} / getArrayCopy() array {"payload":{"b":2,"a":1},"codes":["b","a"],"levels":{"low":1},"mixedKeys":[],"tags":[],"counts":[1,2],"grid":[[1]],"numbers":[1],"later":[],"picked":0}
foreach over a lazy Iterable result reads its source in step: read 1, loop 2, read 2, loop 4
iterator_to_array() of a lazy Iterable<Map<string, int>> result: array,array [{"n":1},{"n":2}]
a lazy Iterable result is a Traversable, and its declared type: true iterable iterable
var_export() of a Map property, a Map and a PHP# object: array (   'b' => 2,   'a' => 1, ) / array (   'a' => 1, ) / \Demo\Carton::__set_state(array(    'payload' =>    array (     'b' => 2,     'a' => 1,   ),    'codes' =>    array (     0 => 'b',     1 => 'a',   ),    'levels' =>    array (     'low' => 1,   ),    'mixedKeys' =>    array (   ),    'tags' =>    array (   ),    'counts' =>    array (     0 => 1,     1 => 2,   ),    'grid' =>    array (     0 =>      array (       0 => 1,     ),   ),    'numbers' =>    array (     0 => 1,   ),    'later' =>    array (   ),    'picked' => 0, ))
plain PHP's writes into a List<int> property, checked when PHP# reads it: append TypeError: Reference held by property Demo\Carton::$counts of type List<int> holds array: [2] expected int, got string 'z' / index write TypeError: Reference held by property Demo\Carton::$counts of type List<int> holds array: [1] expected int, got string 'z' / nested write TypeError: Reference held by property Demo\Carton::$grid of type List<List<int>> holds array: [0][1] expected int, got string 'z' / reference write TypeError: Reference held by property Demo\Carton::$counts of type List<int> holds array: [2] expected int, got string 'z' / array_push() TypeError: Reference held by property Demo\Carton::$counts of type List<int> holds array: [2] expected int, got string 'z' / foreach by reference TypeError: Reference held by property Demo\Carton::$counts of type List<int> holds array: [0] expected int, got string 'z'
plain PHP's writes that fit a List<int> property, and a reference it keeps: 2,3,5 1 / 2,3,5,7 / 2,3,5,7,8,9 [2,3,5,7,8,9] / TypeError: Reference held by property Demo\Carton::$counts of type List<int> holds array: [6] expected int, got string 'z'
plain PHP's writes into a static, an uninitialized and a type parameter's List<int>: static TypeError: Reference held by property Demo\Carton::$totals of type List<int> holds array: [2] expected int, got string 'z' / static that fits 6 / uninitialized TypeError: Reference held by property Demo\Carton::$later of type List<int> holds array: [0] expected int, got string 'z' / type parameter TypeError: Reference held by property Demo\Held::$value of type List<int> holds array: [1] expected int, got string 'z'
