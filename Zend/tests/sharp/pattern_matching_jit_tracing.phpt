--TEST--
PHP# `is`, `is not`, `as` and `match` under the tracing JIT
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
require __DIR__ . '/pattern_matching.inc';
?>
--EXPECT--
jit on
float(12)
float(9)
float(4)
float(0)
float(5)
float(0)
string(7) "nothing"
string(10) "big circle"
string(6) "circle"
string(6) "square"
string(7) "invalid"
string(3) "top"
string(4) "pass"
string(4) "fail"
int(1)
int(2)
int(0)
int(0)
bool(true)
bool(false)
string(5) "first"
string(4) "open"
string(4) "done"
int(2)
int(0)
