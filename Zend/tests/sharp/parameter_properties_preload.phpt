--TEST--
A preloaded PHP# constructor parameter's property runs its set body, its observers and its behaviors
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/parameter_properties_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(ParameterProperties\Ledger::class, false));
$ledger = new ParameterProperties\Ledger('  ada  ', 5, 'gold');
var_dump($ledger->name, $ledger->label, $ledger->changes);
$ledger->total = 6;
var_dump($ledger->changes);
$defaulted = new ParameterProperties\Ledger();
var_dump($defaulted->name, $defaulted->label);
?>
--EXPECT--
bool(true)
string(3) "ada"
string(4) "GOLD"
int(0)
int(1)
string(4) "anon"
string(1) "X"
