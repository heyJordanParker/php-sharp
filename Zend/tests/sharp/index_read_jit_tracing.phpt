--TEST--
A bare PHP# index read throws OutOfRangeException on a missing key under the tracing JIT
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
--FILE--
<?php
echo 'jit ', opcache_get_status()['jit']['on'] ? 'on' : 'off', "\n";
require __DIR__ . '/index_read.inc';
?>
--EXPECT--
jit on
int(20)
OutOfRangeException: Undefined array key 2 in IndexRead.sharp on line 9
OutOfRangeException: Undefined array key -1 in IndexRead.sharp on line 9
int(2)
OutOfRangeException: Undefined array key 2 in IndexRead.sharp on line 14
OutOfRangeException: Undefined array key 1 in IndexRead.sharp on line 14
int(0)
int(5)
int(0)
OutOfRangeException: Undefined array key 2 in IndexRead.sharp on line 30
bool(false)
OutOfRangeException: Undefined array key 2 in IndexRead.sharp on line 36
int(4)
NULL
int(3)
OutOfRangeException: Undefined array key "closed" in MapRead.sharp on line 9
NULL
string(37) "Trying to access array offset on null"
