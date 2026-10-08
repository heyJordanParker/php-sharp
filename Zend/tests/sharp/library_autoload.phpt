--TEST--
A standard library whose native bodies match the engine's boots when autoload.php is required, declares no class until one is used, and loads a .sharp class of any namespace through PSR-4 or the class map
--FILE--
<?php
foreach ([
    ['psr4', 'Sharp\Text\Text'],
    ['classmap', 'Sharp\Text\Text'],
    ['psr4', 'App\Clock'],
    ['classmap', 'App\Clock'],
] as [$layout, $class]) {
    echo "== $layout, $class\n";
    echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n ' . escapeshellarg(__DIR__ . '/library_autoload.inc') . " engine $layout 0.1.0 " . escapeshellarg($class) . ' 2>&1');
}
?>
--EXPECT--
== psr4, Sharp\Text\Text
booted, with no library class declared
class map handed to Composer: []
a-b
== classmap, Sharp\Text\Text
booted, with no library class declared
class map handed to Composer: []
a-b
== psr4, App\Clock
booted, with no library class declared
class map handed to Composer: []
noon
== classmap, App\Clock
booted, with no library class declared
class map handed to Composer: []
noon
