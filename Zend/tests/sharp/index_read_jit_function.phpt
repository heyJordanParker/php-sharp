--TEST--
A bare PHP# index read throws OutOfRangeException on a missing key under the function JIT
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.jit=function
opcache.jit_buffer_size=32M
--FILE--
<?php
echo 'jit ', opcache_get_status()['jit']['on'] ? 'on' : 'off', "\n";
require __DIR__ . '/index_read.inc';
?>
--EXPECT--
jit on
int(20)
OutOfRangeException: Undefined array key 2 in IndexRead.sharp on line 10
OutOfRangeException: Undefined array key -1 in IndexRead.sharp on line 10
int(2)
OutOfRangeException: Undefined array key 2 in IndexRead.sharp on line 15
OutOfRangeException: Undefined array key 1 in IndexRead.sharp on line 15
int(0)
int(5)
int(0)
OutOfRangeException: Undefined array key 2 in IndexRead.sharp on line 31
bool(false)
OutOfRangeException: Undefined array key 2 in IndexRead.sharp on line 37
int(4)
NULL
int(3)
OutOfRangeException: Undefined array key "closed" in IndexRead.sharp on line 47
NULL
string(37) "Trying to access array offset on null"
