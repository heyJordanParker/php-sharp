--TEST--
PHP# + joins two strings under the function JIT
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
require __DIR__ . '/plus.inc';
?>
--EXPECT--
jit on
string(12) "Ada Lovelace"
string(2) "12"
string(10) "12constant"
string(5) "done!"
string(8) "log: a b"
int(5)
string(6) "ababab"
int(3)
