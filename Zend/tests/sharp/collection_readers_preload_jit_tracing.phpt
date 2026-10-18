--TEST--
Plain PHP preloaded with PHP# classes reaches each Map and Set of collection_readers as its PHP array, and each lazy Iterable as a Traversable, under the tracing JIT
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/collection_readers_preload.inc
opcache.jit=tracing
opcache.jit_buffer_size=32M
opcache.jit_hot_func=1
opcache.jit_hot_loop=1
opcache.jit_hot_return=1
opcache.jit_hot_side_exit=1
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
if (ini_get('opcache.jit') === false) die('skip JIT is not available');
?>
--FILE--
<?php
echo 'jit ', opcache_get_status()['jit']['on'] ? 'on' : 'off', "\n";
var_dump(function_exists('readers_rounds'), class_exists(Demo\Carton::class, false));
echo readers_rounds();
?>
--EXPECT--
jit on
bool(true)
bool(true)
ksort() of a Map property: Sharp\Map a,b=1,2 true
a reference to a Map property: array / Sharp\Map b,a,k=2,1,1 / 1 / Sharp\Map / {"b":2,"a":1,"k":1,"j":5} / array {"z":26} / array Sharp\Map x=9 / Sharp\Map x,y=9,25 / true
a write of the wrong type through the reference: TypeError: Cannot assign string to reference held by property Demo\Carton::$payload of type Map<string, int> / TypeError: Cannot assign array to reference held by property Demo\Carton::$payload of type Map<string, int>: ["k"] expected int, got string 'x' / {"b":2,"a":1}
foreach by reference over a Map property: Sharp\Map b,a=3,2
array_walk() by reference over a Map property: Sharp\Map b,a=20,10
sort() of a Set property: Sharp\Set a,b
a plain PHP parent appends to its PHP# subclass's get => field property: password,secret
a PHP array into a Set<string>|int and a Map<string, int>|int parameter: Sharp\Set a,b / int 7 / Sharp\Map 1
an ArrayIterator over a PHP# object: payload array {"b":2,"a":1} / codes array ["b","a"] / current() array {"b":2,"a":1}
foreach over a lazy Iterable result reads its source in step: read 1, loop 2, read 2, loop 4
iterator_to_array() of a lazy Iterable<Map<string, int>> result: array,array [{"n":1},{"n":2}]
a lazy Iterable result is a Traversable, and its declared type: true iterable iterable
var_export() of a Map property, a Map and a PHP# object: array (   'b' => 2,   'a' => 1, ) / array (   'a' => 1, ) / \Demo\Carton::__set_state(array(    'payload' =>    array (     'b' => 2,     'a' => 1,   ),    'codes' =>    array (     0 => 'b',     1 => 'a',   ), ))
