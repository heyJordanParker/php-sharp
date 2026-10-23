--TEST--
PHP# lambdas that the preload request already made, one capturing nothing, one a local, one this, one in a static method, one passed to a plain PHP method, one in a field and one in an interface's default body that two classes implement, run again in each request
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.preload={PWD}/lambdas_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
echo lambda_shapes(), "\n";
echo lambda_shapes(), "\n";
?>
--EXPECT--
8 12 8 8 8 108 label:shapes label:stored
8 12 8 8 8 108 label:shapes label:stored
