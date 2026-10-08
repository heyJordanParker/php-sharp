--TEST--
A standard library that declares a native body the engine lacks refuses when autoload.php is required, naming the body as PHP# writes it and both PHP# versions
--FILE--
<?php
echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n ' . escapeshellarg(__DIR__ . '/library_autoload.inc') . ' missing psr4 0.1.0 ' . escapeshellarg('Sharp\Text\Text') . ' 2>&1');
?>
--EXPECT--
Error: The PHP# standard library 0.1.0 declares the native body Text.missing, which PHP# engine <engine version> does not have. Install the same PHP# version of both.
0 .sharp files included
