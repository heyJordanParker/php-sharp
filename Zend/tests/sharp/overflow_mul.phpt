--TEST--
PHP# throws ArithmeticError when * overflows
--FILE--
<?php

require __DIR__ . '/attempt.inc';
require __DIR__ . '/Overflow.sharp';

attempt(fn () => Demo\Overflow::mul(PHP_INT_MAX, 1));
attempt(fn () => Demo\Overflow::mul(PHP_INT_MAX, 2));
attempt(fn () => Demo\Overflow::mul(PHP_INT_MIN, -1));
attempt(fn () => Demo\Overflow::maxTimesTwo());
attempt(fn () => Demo\Overflow::timesTwo(PHP_INT_MAX >> 1));
attempt(fn () => Demo\Overflow::timesTwo(PHP_INT_MAX));
attempt(fn () => Demo\Overflow::deadProduct(PHP_INT_MAX, 1));
attempt(fn () => Demo\Overflow::deadProduct(PHP_INT_MAX, 2));
?>
--EXPECT--
int(9223372036854775807)
ArithmeticError: Integer overflow in Overflow.sharp on line 17
ArithmeticError: Integer overflow in Overflow.sharp on line 17
ArithmeticError: Integer overflow in Overflow.sharp on line 37
int(9223372036854775806)
ArithmeticError: Integer overflow in Overflow.sharp on line 199
int(9223372036854775807)
ArithmeticError: Integer overflow in Overflow.sharp on line 250
