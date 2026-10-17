--TEST--
PHP# a constructor parameter's property takes a default after its accessors, runs its set body on the argument and on the default, runs its observers after the constructor and not during it, and runs its behaviors
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Behavior.sharp';
require __DIR__ . '/harness/fixture.inc';
fixture('ParameterProperties');

$named = new ParameterProperties\Ledger('  ada  ', 5, 'gold');
var_dump($named->name, $named->total, $named->label, $named->changes);
$named->total = 6;
$named->label = 'silver';
var_dump($named->changes, $named->label);

$defaulted = new ParameterProperties\Ledger();
var_dump($defaulted->name, $defaulted->total, $defaulted->label, $defaulted->changes);

$copy = clone $named;
$copy->label = 'bronze';
var_dump($copy->label, $named->label);
?>
--EXPECT--
string(3) "ada"
int(5)
string(4) "GOLD"
int(0)
int(1)
string(6) "SILVER"
string(4) "anon"
int(0)
string(1) "X"
int(0)
string(6) "BRONZE"
string(6) "SILVER"
