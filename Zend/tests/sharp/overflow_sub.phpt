--TEST--
PHP# throws ArithmeticError when - overflows
--FILE--
<?php

require __DIR__ . '/attempt.inc';
require __DIR__ . '/Overflow.sharp';

attempt(fn () => Demo\Overflow::sub(PHP_INT_MIN + 1, 1));
attempt(fn () => Demo\Overflow::sub(PHP_INT_MIN, 1));
attempt(fn () => Demo\Overflow::sub(PHP_INT_MAX, -1));
attempt(fn () => Demo\Overflow::minMinusOne());
attempt(fn () => Demo\Overflow::negate(PHP_INT_MAX));
attempt(fn () => Demo\Overflow::negate(PHP_INT_MIN));
?>
--EXPECT--
int(-9223372036854775808)
ArithmeticError: Integer overflow in Overflow.sharp on line 12
ArithmeticError: Integer overflow in Overflow.sharp on line 12
ArithmeticError: Integer overflow in Overflow.sharp on line 32
int(-9223372036854775807)
ArithmeticError: Integer overflow in Overflow.sharp on line 22
