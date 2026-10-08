--TEST--
A standard library whose every native body exists in the engine but whose SHARP_NATIVE differs refuses when autoload.php is required, naming the package's version without a leading v, or unknown when Composer does not report it, and the engine's version
--FILE--
<?php
foreach (['0.1.0', 'v0.1.0', 'absent'] as $version) {
    echo "== package $version\n";
    echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n ' . escapeshellarg(__DIR__ . '/library_autoload.inc') . " changed psr4 $version " . escapeshellarg('Sharp\Text\Text') . ' 2>&1');
}
?>
--EXPECT--
== package 0.1.0
Error: The PHP# standard library 0.1.0 was built for other native bodies than PHP# engine <engine version> has. Install the same PHP# version of both.
0 .sharp files included
== package v0.1.0
Error: The PHP# standard library 0.1.0 was built for other native bodies than PHP# engine <engine version> has. Install the same PHP# version of both.
0 .sharp files included
== package absent
Error: The PHP# standard library unknown was built for other native bodies than PHP# engine <engine version> has. Install the same PHP# version of both.
0 .sharp files included
