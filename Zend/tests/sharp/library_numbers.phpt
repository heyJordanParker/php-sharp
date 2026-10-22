--TEST--
A PHP# class calls every member of int and float: abs, clamp, round, floor, ceil, isFinite, toHex and format, abs throws ArithmeticError for the smallest int, and clamp throws ValueError for a minimum above its maximum
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Int.sharp';
require __DIR__ . '/../../../sharp/composer/library/Sharp/Float.sharp';
require __DIR__ . '/NumberCalls.sharp';

use Demo\NumberCalls;

var_dump(NumberCalls::absolute(-7), NumberCalls::absolute(7), NumberCalls::clamped(12, 0, 10), NumberCalls::clamped(-3, 0, 10), NumberCalls::clamped(4, 0, 10));
var_dump(NumberCalls::formatted(1234567, ','), NumberCalls::formatted(-1234567, ' '), NumberCalls::formatted(PHP_INT_MAX, ','), NumberCalls::formatted(999, ','));
var_dump(NumberCalls::absoluteFloat(-1.5), NumberCalls::clampedFloat(2.5, 0.0, 1.0));
var_dump(NumberCalls::rounded(2.5, 0), NumberCalls::rounded(-2.5, 0), NumberCalls::rounded(2.567, 2), NumberCalls::rounded(1234.0, -2));
var_dump(NumberCalls::floored(-1.5), NumberCalls::ceiled(1.2));
var_dump(NumberCalls::formattedFloat(1234.5678, 2), NumberCalls::formattedIn(1234.5678, 1, ',', '.'));
var_dump(NumberCalls::hex(255), NumberCalls::hex(0), NumberCalls::hex(-255), NumberCalls::hex(PHP_INT_MIN),NumberCalls::finite(1.5), NumberCalls::finite(INF), NumberCalls::finite(NAN));

try {
    NumberCalls::absolute(PHP_INT_MIN);
} catch (ArithmeticError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
foreach ([fn () => NumberCalls::clamped(1, 10, 0), fn () => NumberCalls::clampedFloat(1.0, 1.5, 0.5)] as $refused) {
    try {
        $refused();
    } catch (ValueError $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}
?>
--EXPECT--
int(7)
int(7)
int(10)
int(0)
int(4)
string(9) "1,234,567"
string(10) "-1 234 567"
string(25) "9,223,372,036,854,775,807"
string(3) "999"
float(1.5)
float(1)
float(3)
float(-3)
float(2.57)
float(1200)
float(-2)
float(2)
string(8) "1,234.57"
string(7) "1.234,6"
string(2) "ff"
string(1) "0"
string(3) "-ff"
string(17) "-8000000000000000"
bool(true)
bool(false)
bool(false)
ArithmeticError: `abs` of the smallest int is larger than the largest int.
ValueError: `clamp` needs a minimum no larger than its maximum, and the minimum is larger.
ValueError: `clamp` needs a minimum no larger than its maximum, and the minimum is larger.
