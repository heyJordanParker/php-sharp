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
30,10,10,50,90 9,6,5,4,3,2,1,1 3,1,1,5,9 30,10,50,90 tea,7 50,20 100,600,40
19
No element matches the predicate / 3
3,6,7
6,2,8
17
2,4,6
0,102,101
18
30,10,10,50,90 9,6,5,4,3,2,1,1 3,1,1,5,9 30,10,50,90 tea,7 50,20 100,600,40
19
No element matches the predicate / 3
3,6,7
6,2,8
