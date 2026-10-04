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
?>
--EXPECT--
int(9223372036854775807)
ArithmeticError: Integer overflow in Overflow.sharp on line 69
int(9223372036854775807)
ArithmeticError: Integer overflow in Overflow.sharp on line 75
int(9223372036854775806)
ArithmeticError: Integer overflow in Overflow.sharp on line 82
