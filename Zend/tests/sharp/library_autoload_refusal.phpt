--TEST--
A standard library whose native bodies differ from the engine's refuses when autoload.php is required, so the app never prints booted and no .sharp file loads, whether PSR-4 or the class map finds .sharp classes
--FILE--
<?php
foreach (['psr4', 'classmap'] as $layout) {
    echo "== $layout\n";
    echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n ' . escapeshellarg(__DIR__ . '/library_autoload.inc') . " changed $layout 0.1.0 " . escapeshellarg('App\Clock') . ' 2>&1');
}
?>
--EXPECT--
== psr4
Error: The PHP# standard library 0.1.0 was built for other native bodies than PHP# engine <engine version> has. Install the same PHP# version of both.
0 .sharp files included
== classmap
Error: The PHP# standard library 0.1.0 was built for other native bodies than PHP# engine <engine version> has. Install the same PHP# version of both.
0 .sharp files included
