--TEST--
PHP# throws ArithmeticError on overflow under the tracing JIT
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
require __DIR__ . '/overflow_jit.inc';
?>
--EXPECT--
jit on
add 3
ArithmeticError: Integer overflow in Overflow.sharp on line 7
sub -1
ArithmeticError: Integer overflow in Overflow.sharp on line 12
mul 12
ArithmeticError: Integer overflow in Overflow.sharp on line 17
negate -5
ArithmeticError: Integer overflow in Overflow.sharp on line 22
addAssign 3
ArithmeticError: Integer overflow in Overflow.sharp on line 48
subAssign -1
ArithmeticError: Integer overflow in Overflow.sharp on line 55
mulAssign 12
ArithmeticError: Integer overflow in Overflow.sharp on line 62
preInc 2
ArithmeticError: Integer overflow in Overflow.sharp on line 69
postInc 2
ArithmeticError: Integer overflow in Overflow.sharp on line 75
postIncValue 1
ArithmeticError: Integer overflow in Overflow.sharp on line 82
preDec 0
ArithmeticError: Integer overflow in Overflow.sharp on line 88
postDec 0
ArithmeticError: Integer overflow in Overflow.sharp on line 94
postDecValue 1
ArithmeticError: Integer overflow in Overflow.sharp on line 101
ArithmeticError: Integer overflow in Overflow.sharp on line 106
ArithmeticError: Integer overflow in Overflow.sharp on line 117
ArithmeticError: Integer overflow in Overflow.sharp on line 128
ArithmeticError: Integer overflow in Overflow.sharp on line 134
ArithmeticError: Integer overflow in Overflow.sharp on line 142
ArithmeticError: Integer overflow in Overflow.sharp on line 149
ArithmeticError: Integer overflow in Overflow.sharp on line 157
ArithmeticError: Integer overflow in Overflow.sharp on line 117
ArithmeticError: Integer overflow in Overflow.sharp on line 106
int(9223372036854775807)
add loop stopped at 4611686018427387904 on line 7
mul loop stopped at 4052555153018976267 on line 17
preInc loop stopped at 9223372036854775807 on line 69
postDec loop stopped at -9223372036854775808 on line 94
incAfterCall loop stopped at 9223372036854775807 on line 142
addAfterCall loop stopped at 9223372036854775807 on line 149
sumAfterCall loop stopped at 9223372036854775807 on line 157
incProperty loop stopped at 9223372036854775807 on line 106
addProperty loop stopped at 9223372036854775807 on line 117
incLoose loop stopped at 9223372036854775807 on line 128
addLoose loop stopped at 9223372036854775807 on line 134
float(9.223372036854776E+18)
float(9.223372036854776E+18)
array(0) {
}
