--TEST--
PHP# throws ArithmeticError when +=, -= or *= overflows
--FILE--
<?php

require __DIR__ . '/attempt.inc';
require __DIR__ . '/Overflow.sharp';

attempt(fn () => Demo\Overflow::addAssign(PHP_INT_MAX - 1, 1));
attempt(fn () => Demo\Overflow::addAssign(PHP_INT_MAX, 1));
attempt(fn () => Demo\Overflow::subAssign(PHP_INT_MIN, 1));
attempt(fn () => Demo\Overflow::mulAssign(PHP_INT_MAX, 2));
?>
--EXPECT--
int(9223372036854775807)
ArithmeticError: Integer overflow in Overflow.sharp on line 48
ArithmeticError: Integer overflow in Overflow.sharp on line 55
ArithmeticError: Integer overflow in Overflow.sharp on line 62
