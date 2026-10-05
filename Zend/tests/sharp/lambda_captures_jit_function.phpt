--TEST--
PHP# lambdas capture the variables themselves and give each loop pass its own let under the function JIT
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
require __DIR__ . '/lambda_captures.inc';
?>
--EXPECT--
jit on
17
2,4,6
0,102,101
18
17
2,4,6
0,102,101
18
