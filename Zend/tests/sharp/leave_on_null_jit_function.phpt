--TEST--
PHP# `a ?? return`, `a ?? continue` and `a ?? break` under the function JIT
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
