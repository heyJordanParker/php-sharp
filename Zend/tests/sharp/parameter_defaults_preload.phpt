--TEST--
A preloaded PHP# parameter default that calls a method or creates an object runs each time a call leaves the argument out
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/parameter_defaults_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(ParameterDefaults\Ledger::class, false));
$ledger = new ParameterDefaults\Ledger();
var_dump($ledger->made->value, $ledger->seeded(), $ledger->seeded(b: 10), $ledger->stamped(), $ledger->counted());
var_dump(ParameterDefaults\Stamp::hidden(), $ledger->sized());
?>
--EXPECT--
bool(true)
int(41)
int(44)
int(53)
int(7)
int(4)
int(7)
int(8)
