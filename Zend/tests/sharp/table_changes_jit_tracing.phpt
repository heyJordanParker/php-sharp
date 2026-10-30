--TEST--
A Map's and a Set's changes reach where they live and leave a copy as it was, and a member of a value that may be any collection or an object runs the one the value holds, under the tracing JIT
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
require __DIR__ . '/table_changes.inc';
?>
--EXPECT--
jit on
[true,false,true,false] [false,true,false,true]
[1,2] [2,1,3]
[2,1,2,1,1]
bool(true)
bool(false)
{"tea":1,"milk":3} 3
[true,false,true,false,["b","7"]]
1 2 3 7
bool(true)
bool(false)
bool(true)
[true,false,true,false]
