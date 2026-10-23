--TEST--
PHP# a parameter's default is any expression, a property of a new object too, which runs each time a call leaves the argument out, from PHP# and plain PHP, by position and by name
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
require __DIR__ . '/harness/Probe.inc';
require __DIR__ . '/harness/Words.inc';
fixture('ParameterDefaults');

$ledger = new ParameterDefaults\Ledger();
var_dump($ledger->made->value);
var_dump($ledger->seeded(), $ledger->seeded(), $ledger->seeded(1));
var_dump($ledger->seeded(b: 10));
var_dump($ledger->stamped(), $ledger->stamped(new ParameterDefaults\Stamp(9)));
var_dump($ledger->sized(), $ledger->sized(2));
var_dump($ledger->counted(), $ledger->counted('x'));
var_dump(ParameterDefaults\Stamp::hidden());
var_dump($ledger->issued());
var_dump($ledger->plain());
try {
    $ledger->failing();
} catch (LogicException $failure) {
    echo $failure->getMessage(), "\n";
}
var_dump($ledger->failing(3));
?>
--EXPECT--
int(41)
int(44)
int(45)
int(3)
int(54)
int(7)
int(9)
int(8)
int(2)
int(4)
int(1)
int(7)
int(4)
bool(true)
no default
int(3)
