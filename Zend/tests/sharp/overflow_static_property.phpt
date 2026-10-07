--TEST--
PHP# throws ArithmeticError when ++, -- or += overflows a static property
--FILE--
<?php
require __DIR__ . '/overflow_static_property.inc';
?>
--EXPECT--
ArithmeticError: Integer overflow in Tally.sharp on line 10
int(9223372036854775807)
ArithmeticError: Integer overflow in Tally.sharp on line 17
ArithmeticError: Integer overflow in Tally.sharp on line 23
ArithmeticError: Integer overflow in Tally.sharp on line 29
int(-9223372036854775808)
ArithmeticError: Integer overflow in Tally.sharp on line 36
int(3)
int(2)
int(1)
thrown 25 of 50
