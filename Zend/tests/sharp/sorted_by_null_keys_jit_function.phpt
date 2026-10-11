--TEST--
PHP# sortedBy puts a null key before every other key under the function JIT
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
require __DIR__ . '/sorted_by_null_keys.inc';
?>
--EXPECT--
jit on
NULL -2 0 1 3
NULL -2 0 1 3
NULL -2 0 1 3
NULL '' 'B' 'a' 'b'
NULL '' 'B' 'a' 'b'
NULL -2 0 1 3
NULL -2 0 1 3
7 5 -2 0 1 3
5 7 -2 0 1 3
