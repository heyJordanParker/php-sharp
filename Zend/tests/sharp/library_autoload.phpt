--TEST--
The standard library checks SHARP_NATIVE when the first Sharp\ class loads, through PSR-4 or the class map, and not when autoload.php loads, and a mismatch names the package's version
--FILE--
<?php
foreach ([['other', 'psr4', '0.1.0'], ['other', 'classmap', '0.1.0'], ['other', 'psr4', 'absent'], ['engine', 'psr4', '0.1.0'], ['engine', 'classmap', '0.1.0']] as [$fingerprint, $layout, $version]) {
    echo "== $fingerprint SHARP_NATIVE, $layout, package $version\n";
    echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n ' . escapeshellarg(__DIR__ . '/library_autoload.inc') . " $fingerprint $layout $version 2>&1");
}
?>
--EXPECT--
== other SHARP_NATIVE, psr4, package 0.1.0
autoload.php loaded, with no library class declared
class map handed to Composer: []
Error: The PHP# standard library 0.1.0 needs the native bodies of PHP# engine 0.1.0, and this engine is <engine version>. Install the same PHP# version of both.
== other SHARP_NATIVE, classmap, package 0.1.0
autoload.php loaded, with no library class declared
class map handed to Composer: ["Demo\\Elsewhere"]
Error: The PHP# standard library 0.1.0 needs the native bodies of PHP# engine 0.1.0, and this engine is <engine version>. Install the same PHP# version of both.
== other SHARP_NATIVE, psr4, package absent
autoload.php loaded, with no library class declared
class map handed to Composer: []
Error: The PHP# standard library unknown needs the native bodies of PHP# engine unknown, and this engine is <engine version>. Install the same PHP# version of both.
== engine SHARP_NATIVE, psr4, package 0.1.0
autoload.php loaded, with no library class declared
class map handed to Composer: []
a-b
== engine SHARP_NATIVE, classmap, package 0.1.0
autoload.php loaded, with no library class declared
class map handed to Composer: ["Demo\\Elsewhere"]
a-b
