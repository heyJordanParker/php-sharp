--TEST--
PHP# throws ArithmeticError when ++, -- or += overflows a static property under the function JIT
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.jit=function
opcache.jit_buffer_size=32M
--FILE--
<?php
echo opcache_get_status()['jit']['on'] ? "jit on\n" : "jit off\n";
require __DIR__ . '/overflow_static_property.inc';
?>
--EXPECT--
jit on
ArithmeticError: Integer overflow in Tally.sharp on line 10
int(9223372036854775807)
ArithmeticError: Integer overflow in Tally.sharp on line 17
ArithmeticError: Integer overflow in Tally.sharp on line 23
ArithmeticError: Integer overflow in Tally.sharp on line 29
int(-9223372036854775808)
ArithmeticError: Integer overflow in Tally.sharp on line 36
int(3)
int(2)
int(1)
thrown 25 of 50
