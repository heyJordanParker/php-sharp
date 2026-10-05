--TEST--
PHP# throws ArithmeticError when ** or **= overflows, and keeps PHP's float for a negative exponent
--FILE--
<?php
require __DIR__ . '/overflow_pow.inc';
Demo\powCases();
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
int(9223372030926249001)
ArithmeticError: Integer overflow in Overflow.sharp on line 313
int(3037000500)
ArithmeticError: Integer overflow in Overflow.sharp on line 319
int(2)
ArithmeticError: Integer overflow in Overflow.sharp on line 325
