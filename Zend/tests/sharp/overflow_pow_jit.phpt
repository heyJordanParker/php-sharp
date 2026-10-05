--TEST--
PHP# throws ArithmeticError when ** or **= overflows under the tracing JIT
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.jit=tracing
opcache.jit_buffer_size=32M
opcache.jit_hot_func=1
opcache.jit_hot_loop=1
opcache.jit_hot_return=1
opcache.jit_hot_side_exit=1
--FILE--
<?php

require __DIR__ . '/attempt.inc';
require __DIR__ . '/Overflow.sharp';

echo 'jit ', opcache_get_status()['jit']['on'] ? 'on' : 'off', "\n";

for ($round = 0; $round < 2; $round++) {
    attempt(fn () => Demo\Overflow::power(2, 62));
    attempt(fn () => Demo\Overflow::power(2, 63));
    attempt(fn () => Demo\Overflow::powAssign(3037000499));
    attempt(fn () => Demo\Overflow::powAssign(3037000500));
    attempt(fn () => Demo\Overflow::inverse(2, -1));
}
?>
--EXPECT--
jit on
int(4611686018427387904)
ArithmeticError: Integer overflow in Overflow.sharp on line 256
int(9223372030926249001)
ArithmeticError: Integer overflow in Overflow.sharp on line 267
float(0.5)
int(4611686018427387904)
ArithmeticError: Integer overflow in Overflow.sharp on line 256
int(9223372030926249001)
ArithmeticError: Integer overflow in Overflow.sharp on line 267
float(0.5)
