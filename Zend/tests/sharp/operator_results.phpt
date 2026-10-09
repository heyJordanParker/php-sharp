--TEST--
PHP# == compares strictly, strings order by their bytes, bitwise operators take ints, a class's declared operators run, == is lifted over null without running the class's operator, and sortedBy orders keys as <=> does
--FILE--
<?php
require __DIR__ . '/operator_results.inc';
?>
--EXPECT--
bool(false)
bool(false)
bool(true)
bool(true)
bool(true)
ArithmeticError: Bit shift by negative number in Operators.sharp on line 165
int(0)
int(8)
int(12)
int(5)
int(2)
string(7) "800 EUR"
string(7) "200 EUR"
string(8) "1500 EUR"
string(7) "166 EUR"
string(5) "2 EUR"
string(10) "250000 EUR"
string(8) "-500 EUR"
bool(true)
int(1)
string(5) "1 EUR"
bool(true)
bool(true)
bool(false)
int(2)
bool(true)
bool(false)
bool(true)
bool(false)
bool(true)
int(0)
string(5) "c a b"
string(16) "10 9 Zebra apple"
