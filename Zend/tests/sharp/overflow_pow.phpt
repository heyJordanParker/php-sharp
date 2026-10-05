--TEST--
PHP# throws ArithmeticError when ** or **= overflows, and keeps PHP's float for a negative exponent
--FILE--
<?php

require __DIR__ . '/attempt.inc';
require __DIR__ . '/Overflow.sharp';

attempt(fn () => Demo\Overflow::power(2, 62));
attempt(fn () => Demo\Overflow::power(2, 63));
attempt(fn () => Demo\Overflow::power(-2, 63));
attempt(fn () => Demo\Overflow::power(-2, 64));
attempt(fn () => Demo\Overflow::maxSquared());
attempt(fn () => Demo\Overflow::powAssign(3037000499));
attempt(fn () => Demo\Overflow::powAssign(3037000500));
attempt(fn () => Demo\Overflow::inverse(2, -1));
attempt(fn () => Demo\Overflow::half());
?>
--EXPECT--
int(4611686018427387904)
ArithmeticError: Integer overflow in Overflow.sharp on line 256
int(-9223372036854775808)
ArithmeticError: Integer overflow in Overflow.sharp on line 256
ArithmeticError: Integer overflow in Overflow.sharp on line 261
int(9223372030926249001)
ArithmeticError: Integer overflow in Overflow.sharp on line 267
float(0.5)
float(0.5)
