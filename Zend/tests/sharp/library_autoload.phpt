--TEST--
The standard library checks SHARP_NATIVE when the first Sharp\ class loads, through PSR-4 or the class map, and not when autoload.php loads
--FILE--
<?php
foreach ([['other', 'psr4'], ['other', 'classmap'], ['engine', 'psr4'], ['engine', 'classmap']] as [$fingerprint, $layout]) {
    echo "== $fingerprint SHARP_NATIVE, $layout\n";
    echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n ' . escapeshellarg(__DIR__ . '/library_autoload.inc') . " $fingerprint $layout 2>&1");
}
?>
--EXPECT--
== other SHARP_NATIVE, psr4
autoload.php loaded, with no library class declared
class map handed to Composer: []
Error: The PHP# standard library in vendor/ was built for other native bodies than this engine has (library 0123456789abcdef0123456789abcdef, engine <engine>). Install the heyjordanparker/php-sharp-composer version that matches this PHP# build.
== other SHARP_NATIVE, classmap
autoload.php loaded, with no library class declared
class map handed to Composer: ["Demo\\Elsewhere"]
Error: The PHP# standard library in vendor/ was built for other native bodies than this engine has (library 0123456789abcdef0123456789abcdef, engine <engine>). Install the heyjordanparker/php-sharp-composer version that matches this PHP# build.
== engine SHARP_NATIVE, psr4
autoload.php loaded, with no library class declared
class map handed to Composer: []
a-b
== engine SHARP_NATIVE, classmap
autoload.php loaded, with no library class declared
class map handed to Composer: ["Demo\\Elsewhere"]
a-b
