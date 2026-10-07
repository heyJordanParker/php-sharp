--TEST--
PHP# (int) truncates a float and throws ArithmeticError on NaN, infinity and a float out of int range
--FILE--
<?php
require __DIR__ . '/cast.inc';
?>
--EXPECT--
int(1250)
int(-75)
int(-7)
int(-9223372036854775808)
float(0.25)
string(2) "42"
ArithmeticError: The float NAN is not representable as an int in Checkout.sharp on line 27
ArithmeticError: The float INF is not representable as an int in Checkout.sharp on line 27
ArithmeticError: The float -INF is not representable as an int in Checkout.sharp on line 27
ArithmeticError: The float 9.223372036854776E+18 is not representable as an int in Checkout.sharp on line 27
ArithmeticError: The float -1.0E+19 is not representable as an int in Checkout.sharp on line 27
sum 11175, thrown 50
ArithmeticError: The float 1.0E+19 is not representable as an int in Checkout.sharp on line 22
int(9)
int(12)
