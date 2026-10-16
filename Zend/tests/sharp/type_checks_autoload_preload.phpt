--TEST--
A type check loads a class during the preload request, which preloading keeps, and a request asks the autoloader only for the classes preloading didn't load
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/type_checks_autoload_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(Orchard\Apple::class, false), class_exists(Orchard\Ripeness::class, false));
require __DIR__ . '/type_checks_autoload.inc';
require __DIR__ . '/type_checks_autoload_rows.inc';
?>
--EXPECTF--
bool(true)
bool(true)
an out type argument: weighed
a backed enum key: 3, and $stock after the write: 3
a function type: true
an autoloader that throws: RuntimeException: The autoloader failed to load Orchard\Quince.
a class with no file: TypeError: Orchard\Picker::weigh(): Argument #1 ($harvest) must be of type Orchard.Harvest<Orchard.Fruit>, Orchard.Crate<Orchard.Medlar> given, called in %s on line %d
asked: Orchard\Tray, Orchard\Pear, Orchard\Quince, Orchard\Medlar, Orchard\Medlar
