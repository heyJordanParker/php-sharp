--TEST--
On a fresh clone, before anything is compiled, requiring autoload.php boots, so vendor/bin/mago compile and a mago extension host can load it, and once mago compiles the project, the container builds a service through the library's module
--FILE--
<?php
echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n ' . escapeshellarg(__DIR__ . '/library_autoload.inc') . ' engine psr4 0.1.0 ' . escapeshellarg('App\Greeting') . ' uncompiled 2>&1');
?>
--EXPECT--
booted, with no library class declared
class map handed to Composer: []
no greeting
