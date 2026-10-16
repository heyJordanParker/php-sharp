--TEST--
A standard library whose native bodies match the engine's boots when autoload.php is required, declares no class until one is used, and loads a .sharp class of any namespace through PSR-4 or the class map, a case of an enum that carries data through its enum's file, and an interface's Defaults trait through the interface's file, which is read once
--FILE--
<?php
foreach ([
    ['psr4', 'Sharp\Text\Text'],
    ['classmap', 'Sharp\Text\Text'],
    ['psr4', 'App\Clock'],
    ['classmap', 'App\Clock'],
    ['psr4', 'App\Tick\Now'],
    ['classmap', 'App\Tick\Now'],
    ['psr4', 'App\Timed\Defaults'],
    ['classmap', 'App\Timed\Defaults'],
    ['psr4', 'App\Ticking\Defaults'],
    ['classmap', 'App\Ticking\Defaults'],
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
class map the plugin writes: ["App\\Clock","App\\Tick","App\\Tick\\Later","App\\Tick\\Now","App\\Timed","App\\Timed\\Defaults","App\\Ticking","App\\Ticking\\Defaults","Sharp\\Text\\Text"]
booted, with no library class declared
class map handed to Composer: []
a-b
== psr4, App\Clock
booted, with no library class declared
class map handed to Composer: []
noon
== classmap, App\Clock
class map the plugin writes: ["App\\Clock","App\\Tick","App\\Tick\\Later","App\\Tick\\Now","App\\Timed","App\\Timed\\Defaults","App\\Ticking","App\\Ticking\\Defaults","Sharp\\Text\\Text"]
booted, with no library class declared
class map handed to Composer: []
noon
== psr4, App\Tick\Now
booted, with no library class declared
class map handed to Composer: []
noon, with its enum
== classmap, App\Tick\Now
class map the plugin writes: ["App\\Clock","App\\Tick","App\\Tick\\Later","App\\Tick\\Now","App\\Timed","App\\Timed\\Defaults","App\\Ticking","App\\Ticking\\Defaults","Sharp\\Text\\Text"]
booted, with no library class declared
class map handed to Composer: []
noon, with its enum
== psr4, App\Timed\Defaults
booted, with no library class declared
class map handed to Composer: []
default noon
== classmap, App\Timed\Defaults
class map the plugin writes: ["App\\Clock","App\\Tick","App\\Tick\\Later","App\\Tick\\Now","App\\Timed","App\\Timed\\Defaults","App\\Ticking","App\\Ticking\\Defaults","Sharp\\Text\\Text"]
booted, with no library class declared
class map handed to Composer: []
default noon
== psr4, App\Ticking\Defaults
booted, with no library class declared
class map handed to Composer: []
Error: Trait "App\Ticking\Defaults" not found
1 .sharp files included
== classmap, App\Ticking\Defaults
class map the plugin writes: ["App\\Clock","App\\Tick","App\\Tick\\Later","App\\Tick\\Now","App\\Timed","App\\Timed\\Defaults","App\\Ticking","App\\Ticking\\Defaults","Sharp\\Text\\Text"]
booted, with no library class declared
class map handed to Composer: []
Error: Trait "App\Ticking\Defaults" not found
1 .sharp files included
