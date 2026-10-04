--TEST--
PHP# throws ArithmeticError when -- overflows
--FILE--
<?php

require __DIR__ . '/attempt.inc';
require __DIR__ . '/Overflow.sharp';

attempt(fn () => Demo\Overflow::preDec(PHP_INT_MIN + 1));
attempt(fn () => Demo\Overflow::preDec(PHP_INT_MIN));
attempt(fn () => Demo\Overflow::postDec(PHP_INT_MIN + 1));
attempt(fn () => Demo\Overflow::postDec(PHP_INT_MIN));
attempt(fn () => Demo\Overflow::postDecValue(PHP_INT_MIN + 1));
attempt(fn () => Demo\Overflow::postDecValue(PHP_INT_MIN));
?>
--EXPECT--
int(-9223372036854775808)
ArithmeticError: Integer overflow in Overflow.sharp on line 88
int(-9223372036854775808)
ArithmeticError: Integer overflow in Overflow.sharp on line 94
int(-9223372036854775807)
ArithmeticError: Integer overflow in Overflow.sharp on line 101
