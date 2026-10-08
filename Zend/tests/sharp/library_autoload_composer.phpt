--TEST--
Composer itself requires autoload.php when it loads the package as a plugin, and it checks neither the engine nor the native bodies there, so composer install and composer update run on plain PHP and on an engine the installed library does not match
--FILE--
<?php
foreach ([
    'plain PHP' => ['engine', '-d ' . escapeshellarg('disable_functions=sharp\internal\requirenative')],
    'other native bodies' => ['changed', ''],
    'a missing native body' => ['missing', ''],
] as $case => [$native, $settings]) {
    echo "== $case\n";
    echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . " -n $settings " . escapeshellarg(__DIR__ . '/library_autoload.inc') . " $native psr4 0.1.0 composer 2>&1");
}
?>
--EXPECT--
== plain PHP
booted, with no library class declared
class map handed to Composer: []
== other native bodies
booted, with no library class declared
class map handed to Composer: []
== a missing native body
booted, with no library class declared
class map handed to Composer: []
