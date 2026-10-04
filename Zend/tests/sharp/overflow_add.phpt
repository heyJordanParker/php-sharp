--TEST--
PHP# throws ArithmeticError when + overflows
--FILE--
<?php

require __DIR__ . '/attempt.inc';
require __DIR__ . '/Overflow.sharp';

attempt(fn () => Demo\Overflow::add(PHP_INT_MAX - 1, 1));
attempt(fn () => Demo\Overflow::add(PHP_INT_MAX, 1));
attempt(fn () => Demo\Overflow::add(PHP_INT_MIN, -1));
attempt(fn () => Demo\Overflow::maxPlusOne());
attempt(fn () => Demo\Overflow::literalPlusOne());
?>
--EXPECT--
int(9223372036854775807)
ArithmeticError: Integer overflow in Overflow.sharp on line 7
ArithmeticError: Integer overflow in Overflow.sharp on line 7
ArithmeticError: Integer overflow in Overflow.sharp on line 27
ArithmeticError: Integer overflow in Overflow.sharp on line 42
