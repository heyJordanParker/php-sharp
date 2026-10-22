--TEST--
A standard library that declares a native body the engine lacks refuses when autoload.php is required, naming both PHP# versions
--FILE--
<?php
echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n ' . escapeshellarg(__DIR__ . '/library_autoload.inc') . ' missing psr4 0.1.0 ' . escapeshellarg('Sharp\Text\StringMembers') . ' 2>&1');
?>
--EXPECT--
Error: This project's PHP# library is 0.1.0, and the PHP# engine is <engine version>. Install PHP# 0.1.0, or require heyjordanparker/php-sharp-composer <engine version>.
0 .sharp files included
