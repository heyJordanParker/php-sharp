--TEST--
PHP# throws ArithmeticError when int ** int or **= overflows or has a negative exponent, while a float operand and plain PHP give a float
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
ArithmeticError: Integer raised to a negative power in Overflow.sharp on line 256
ArithmeticError: Integer raised to a negative power in Overflow.sharp on line 256
ArithmeticError: Integer raised to a negative power in Overflow.sharp on line 342
ArithmeticError: Integer raised to a negative power in Overflow.sharp on line 349
float(0.5)
float(0.5)
float(0.25)
float(0.5)
float(0.5)
int(9223372030926249001)
ArithmeticError: Integer overflow in Overflow.sharp on line 313
int(3037000500)
ArithmeticError: Integer overflow in Overflow.sharp on line 319
ArithmeticError: Integer raised to a negative power in Overflow.sharp on line 313
int(2)
ArithmeticError: Integer raised to a negative power in Overflow.sharp on line 319
int(2)
ArithmeticError: Integer overflow in Overflow.sharp on line 325
ArithmeticError: Integer raised to a negative power in Overflow.sharp on line 325
ArithmeticError: Integer raised to a negative power in Overflow.sharp on line 307
