--TEST--
PHP# lambdas capture the variables themselves and give each loop pass its own let under the tracing JIT
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
