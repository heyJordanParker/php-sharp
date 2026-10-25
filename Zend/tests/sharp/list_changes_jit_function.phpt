--TEST--
A List's removeLast, removeFirst and removeAt change the List where it lives, a property with a set body through its set, under the function JIT
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.jit=function
opcache.jit_buffer_size=32M
--SKIPIF--
<?php
if (ini_get('opcache.jit') === false) die('skip JIT is not available');
?>
--FILE--
<?php
echo 'jit ', opcache_get_status()['jit']['on'] ? 'on' : 'off', "\n";
require __DIR__ . '/list_changes.inc';
?>
--EXPECT--
jit on
c ["a","b"]
b ["a"]
y ["x"] 2
old ["new"]
["b","a","c"]
["b","c"]
only
[true,false] [false,true]
NULL
int(1)
[true,false,"b"]
OutOfRangeException: The list is empty.
OutOfRangeException: The list is empty.
OutOfRangeException: Undefined array key 3
OutOfRangeException: The list is empty.
