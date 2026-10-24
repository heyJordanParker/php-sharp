--TEST--
A Map finds a key, and a Set an element, by PHP#'s ==, and keeps the key it first stored, preloaded, under the tracing JIT
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/table_keys_preload.inc
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
var_dump(function_exists('table_keys_rounds'), class_exists(Demo\Spot::class, false));
echo table_keys_rounds();
?>
--EXPECT--
jit on
bool(true)
bool(true)
an int and its float: 5 => b
zero and minus zero: 0.0 => c
NAN is one key: NAN => b
a numeric string is not an int: 5 => a, '5' => b
true, false and null: true => d, false => b, NULL => c, 1 => e
an enum case: Warm => c, Cool => b
a declared ==: Cents{"amount":5,"label":"first"} => b, Cents{"amount":6,"label":"third"} => c
a struct, by its whole value: Demo\Spot{"x":1,"y":2} => b, Demo\Spot{"x":2,"y":1} => c
siblings share the == they inherit: Guest{"id":1} => b, Host{"id":2} => c
an unrelated class with its own ==: Guest{"id":1} => a, Stranger{"id":1} => b
a chain of equal hashes: Clash{"id":1} => a, Clash{"id":2} => d, Clash{"id":3} => c
an object without ==: Plain[] => c, Plain[] => b
a List: array (
  0 => 1,
  1 => 2,
) => b, array (
  0 => 2,
  1 => 1,
) => c
a Map, regardless of order: Sharp\Map{"1":"x","2":"y"} => b
a Set, regardless of order: Sharp\Set[1,2] => b, Sharp\Set[1] => c
a removal inside a chain: acNULL cNULL 1
a key under another hash is never compared: a 1, in a List: a 1
a Set: 3 true false
NAN in a Set: 2 true, in a Map: 1 'b', zeros: 1
ints and floats: 4 false
a read: tea NULL
a resource: A Map key or a Set element can't be of type resource
