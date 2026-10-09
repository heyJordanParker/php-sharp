--TEST--
On plain PHP, requiring autoload.php says the project needs the PHP# engine, before the app prints booted and before any .sharp file loads
--FILE--
<?php
foreach (['psr4', 'classmap'] as $layout) {
    echo "== $layout\n";
    echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -d ' . escapeshellarg('disable_functions=sharp\internal\requirenative')
        . ' ' . escapeshellarg(__DIR__ . '/library_autoload.inc') . " engine $layout 0.1.0 " . escapeshellarg('App\Clock') . ' 2>&1');
}
?>
--EXPECT--
== psr4
Error: This project needs the PHP# engine, and this is plain PHP. Install php-sharp, or run the ghcr.io/heyjordanparker/php-sharp image.
0 .sharp files included
== classmap
Error: This project needs the PHP# engine, and this is plain PHP. Install php-sharp, or run the ghcr.io/heyjordanparker/php-sharp image.
0 .sharp files included
