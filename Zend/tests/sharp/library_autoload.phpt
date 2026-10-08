--TEST--
The standard library checks SHARP_NATIVE when the first .sharp class of any namespace loads, through PSR-4 or the class map, and not when autoload.php loads, and a mismatch names the package's version
--FILE--
<?php
foreach ([
    ['other', 'psr4', '0.1.0', 'Sharp\Text\Text'],
    ['other', 'classmap', '0.1.0', 'Sharp\Text\Text'],
    ['other', 'psr4', 'absent', 'Sharp\Text\Text'],
    ['other', 'psr4', 'v0.1.0', 'Sharp\Text\Text'],
    ['other', 'psr4', '0.1.0', 'App\Clock'],
    ['other', 'classmap', '0.1.0', 'App\Clock'],
    ['engine', 'psr4', '0.1.0', 'Sharp\Text\Text'],
    ['engine', 'classmap', '0.1.0', 'Sharp\Text\Text'],
    ['engine', 'psr4', '0.1.0', 'App\Clock'],
    ['engine', 'classmap', '0.1.0', 'App\Clock'],
] as [$fingerprint, $layout, $version, $class]) {
    echo "== $fingerprint SHARP_NATIVE, $layout, package $version, $class\n";
    echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n ' . escapeshellarg(__DIR__ . '/library_autoload.inc') . " $fingerprint $layout $version " . escapeshellarg($class) . ' 2>&1');
}
?>
--EXPECT--
== other SHARP_NATIVE, psr4, package 0.1.0, Sharp\Text\Text
autoload.php loaded, with no library class declared
class map handed to Composer: []
Error: The PHP# standard library 0.1.0 needs the native bodies of PHP# engine 0.1.0, and this engine is <engine version>. Install the same PHP# version of both.
== other SHARP_NATIVE, classmap, package 0.1.0, Sharp\Text\Text
autoload.php loaded, with no library class declared
class map handed to Composer: []
Error: The PHP# standard library 0.1.0 needs the native bodies of PHP# engine 0.1.0, and this engine is <engine version>. Install the same PHP# version of both.
== other SHARP_NATIVE, psr4, package absent, Sharp\Text\Text
autoload.php loaded, with no library class declared
class map handed to Composer: []
Error: The PHP# standard library unknown needs the native bodies of PHP# engine unknown, and this engine is <engine version>. Install the same PHP# version of both.
== other SHARP_NATIVE, psr4, package v0.1.0, Sharp\Text\Text
autoload.php loaded, with no library class declared
class map handed to Composer: []
Error: The PHP# standard library 0.1.0 needs the native bodies of PHP# engine 0.1.0, and this engine is <engine version>. Install the same PHP# version of both.
== other SHARP_NATIVE, psr4, package 0.1.0, App\Clock
autoload.php loaded, with no library class declared
class map handed to Composer: []
Error: The PHP# standard library 0.1.0 needs the native bodies of PHP# engine 0.1.0, and this engine is <engine version>. Install the same PHP# version of both.
== other SHARP_NATIVE, classmap, package 0.1.0, App\Clock
autoload.php loaded, with no library class declared
class map handed to Composer: []
Error: The PHP# standard library 0.1.0 needs the native bodies of PHP# engine 0.1.0, and this engine is <engine version>. Install the same PHP# version of both.
== engine SHARP_NATIVE, psr4, package 0.1.0, Sharp\Text\Text
autoload.php loaded, with no library class declared
class map handed to Composer: []
a-b
== engine SHARP_NATIVE, classmap, package 0.1.0, Sharp\Text\Text
autoload.php loaded, with no library class declared
class map handed to Composer: []
a-b
== engine SHARP_NATIVE, psr4, package 0.1.0, App\Clock
autoload.php loaded, with no library class declared
class map handed to Composer: []
noon
== engine SHARP_NATIVE, classmap, package 0.1.0, App\Clock
autoload.php loaded, with no library class declared
class map handed to Composer: []
noon
