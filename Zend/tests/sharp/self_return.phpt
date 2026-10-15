--TEST--
PHP# Self is the class a static method is called on: new Self(…) creates it and Self.m() calls its static, below a required constructor
--FILE--
<?php

require __DIR__ . '/SelfReturn.sharp';

foreach (['Classes\Ledger', 'Classes\Archive'] as $class) {
    $made = $class::fromSchema('r1');
    var_dump(get_class($made), $made->row, $class::kindOfSelf());
}
?>
--EXPECT--
string(14) "Classes\Ledger"
string(2) "r1"
string(6) "ledger"
string(15) "Classes\Archive"
string(2) "r1"
string(6) "ledger"
