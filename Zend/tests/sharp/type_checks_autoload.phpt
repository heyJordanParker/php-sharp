--TEST--
A type check loads a class the first time it needs that class's declaration: an out type argument, a backed enum key and a function type, the autoloader's own exception reaching the caller and a class it finds no file for giving the TypeError
--FILE--
<?php
require __DIR__ . '/type_checks_autoload.inc';
require __DIR__ . '/type_checks_autoload_rows.inc';
?>
--EXPECTF--
an out type argument: weighed
a backed enum key: 3, and $stock after the write: 99
a function type: true
an autoloader that throws: RuntimeException: The autoloader failed to load Orchard\Quince.
a class with no file: TypeError: Orchard\Picker::weigh(): Argument #1 ($harvest) must be of type Orchard.Harvest<Orchard.Fruit>, Orchard.Crate<Orchard.Medlar> given, called in %s on line %d
asked: Orchard\Picker, Orchard\Crate, Orchard\Harvest, Orchard\Apple, Orchard\Fruit, Orchard\Stall, Orchard\Ripeness, Orchard\Tray, Orchard\Pear, Orchard\Quince, Orchard\Medlar, Orchard\Medlar
