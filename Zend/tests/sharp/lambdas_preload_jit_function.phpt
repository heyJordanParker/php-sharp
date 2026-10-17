--TEST--
PHP# lambdas that the preload request already made run again in each request under the function JIT
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.preload={PWD}/lambdas_preload.inc
opcache.jit=function
opcache.jit_buffer_size=32M
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
echo 'jit ', opcache_get_status()['jit']['on'] ? 'on' : 'off', "\n";
echo lambda_shapes(), "\n";
echo lambda_shapes(), "\n";
?>
--EXPECT--
jit on
8 12 8 8 8 108
8 12 8 8 8 108
