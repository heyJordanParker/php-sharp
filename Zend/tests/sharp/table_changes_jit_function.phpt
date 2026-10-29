--TEST--
A Map's and a Set's changes reach where they live and leave a copy as it was, and a member of a value that may be any collection or an object runs the one the value holds, under the function JIT
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
