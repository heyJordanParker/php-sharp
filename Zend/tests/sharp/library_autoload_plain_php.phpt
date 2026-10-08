--TEST--
On plain PHP, the first Sharp\ class says the project needs the PHP# engine, and autoload.php itself loads quietly
--FILE--
<?php
echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -d ' . escapeshellarg('disable_functions=sharp\internal\requirenative')
    . ' ' . escapeshellarg(__DIR__ . '/library_autoload.inc') . ' engine psr4 0.1.0 2>&1');
?>
--EXPECT--
autoload.php loaded, with no library class declared
class map handed to Composer: []
Error: This project needs the PHP# engine, and this is plain PHP. Install php-sharp, or run the ghcr.io/heyjordanparker/php-sharp image.
