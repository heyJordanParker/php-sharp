--TEST--
PHP# throws ArithmeticError when ++ overflows
--FILE--
<?php

require __DIR__ . '/attempt.inc';
require __DIR__ . '/Overflow.sharp';

attempt(fn () => Demo\Overflow::preInc(PHP_INT_MAX - 1));
attempt(fn () => Demo\Overflow::preInc(PHP_INT_MAX));
attempt(fn () => Demo\Overflow::postInc(PHP_INT_MAX - 1));
attempt(fn () => Demo\Overflow::postInc(PHP_INT_MAX));
attempt(fn () => Demo\Overflow::postIncValue(PHP_INT_MAX - 1));
attempt(fn () => Demo\Overflow::postIncValue(PHP_INT_MAX));
attempt(fn () => Demo\Overflow::selfAddOne(PHP_INT_MAX - 1));
attempt(fn () => Demo\Overflow::selfAddOne(PHP_INT_MAX));
attempt(fn () => Demo\Overflow::oneAddSelf(PHP_INT_MAX - 1));
attempt(fn () => Demo\Overflow::oneAddSelf(PHP_INT_MAX));
attempt(fn () => Demo\Overflow::addAssignOne(PHP_INT_MAX - 1));
attempt(fn () => Demo\Overflow::addAssignOne(PHP_INT_MAX));
attempt(fn () => Demo\Overflow::constInc());
attempt(fn () => Demo\Overflow::constantIncrement());
attempt(fn () => Demo\Overflow::deadIncrement(PHP_INT_MAX - 1));
attempt(fn () => Demo\Overflow::deadIncrement(PHP_INT_MAX));
?>
--EXPECT--
int(9223372036854775807)
ArithmeticError: Integer overflow in Overflow.sharp on line 69
int(9223372036854775807)
ArithmeticError: Integer overflow in Overflow.sharp on line 75
int(9223372036854775806)
ArithmeticError: Integer overflow in Overflow.sharp on line 82
int(9223372036854775807)
ArithmeticError: Integer overflow in Overflow.sharp on line 172
int(9223372036854775807)
ArithmeticError: Integer overflow in Overflow.sharp on line 179
int(9223372036854775807)
ArithmeticError: Integer overflow in Overflow.sharp on line 186
ArithmeticError: Integer overflow in Overflow.sharp on line 205
ArithmeticError: Integer overflow in Overflow.sharp on line 218
int(9223372036854775806)
ArithmeticError: Integer overflow in Overflow.sharp on line 231
