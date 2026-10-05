--TEST--
PHP# throws ArithmeticError when +=, -=, *= or **= overflows, even when its result is never read
--FILE--
<?php

require __DIR__ . '/attempt.inc';
require __DIR__ . '/Overflow.sharp';

attempt(fn () => Demo\Overflow::addAssign(PHP_INT_MAX - 1, 1));
attempt(fn () => Demo\Overflow::addAssign(PHP_INT_MAX, 1));
attempt(fn () => Demo\Overflow::subAssign(PHP_INT_MIN, 1));
attempt(fn () => Demo\Overflow::mulAssign(PHP_INT_MAX, 2));
attempt(fn () => Demo\Overflow::constantAssign());
attempt(fn () => Demo\Overflow::deadAddAssign(PHP_INT_MAX, 0));
attempt(fn () => Demo\Overflow::deadAddAssign(PHP_INT_MAX, 1));
attempt(fn () => Demo\Overflow::deadSubAssign(PHP_INT_MIN, 1));
attempt(fn () => Demo\Overflow::deadMulAssign(PHP_INT_MAX, 2));
attempt(fn () => Demo\Overflow::deadPowAssign(2, 63));
?>
--EXPECT--
int(9223372036854775807)
ArithmeticError: Integer overflow in Overflow.sharp on line 48
ArithmeticError: Integer overflow in Overflow.sharp on line 55
ArithmeticError: Integer overflow in Overflow.sharp on line 62
ArithmeticError: Integer overflow in Overflow.sharp on line 224
int(9223372036854775807)
ArithmeticError: Integer overflow in Overflow.sharp on line 286
ArithmeticError: Integer overflow in Overflow.sharp on line 293
ArithmeticError: Integer overflow in Overflow.sharp on line 300
ArithmeticError: Integer overflow in Overflow.sharp on line 307
