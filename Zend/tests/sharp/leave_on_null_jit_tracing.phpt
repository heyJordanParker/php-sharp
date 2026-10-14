--TEST--
PHP# `a ?? return`, `a ?? continue` and `a ?? break` under the tracing JIT
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
require __DIR__ . '/leave_on_null.inc';
?>
--EXPECT--
jit on
int(10)
int(-1)
int(2)
string(12) "<pro> is Pro"
string(18) "<basic> is missing"
float(2)
NULL
int(8)
int(6)
int(5)
int(10)
int(-2)
int(10)
int(2)
int(-3)
