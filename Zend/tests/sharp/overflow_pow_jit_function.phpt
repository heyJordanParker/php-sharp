--TEST--
PHP# throws ArithmeticError when int ** int or **= overflows or has a negative exponent under the function JIT
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.jit=function
opcache.jit_buffer_size=32M
--FILE--
<?php
echo 'jit ', opcache_get_status()['jit']['on'] ? 'on' : 'off', "\n";
require __DIR__ . '/overflow_pow.inc';
Demo\powCases();
?>
--EXPECT--
jit on
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
