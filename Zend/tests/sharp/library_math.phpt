--TEST--
A PHP# class calls every method of Math, which call the PHP functions of their own names, Math.max and Math.min take one int or more, spread or not, and Math.pow throws DivisionByZeroError for a base of 0 and a negative exponent
--FILE--
<?php
require __DIR__ . '/library_math.inc';

use Demo\MathCalls;

var_dump(MathCalls::sqrt(16.0), MathCalls::sqrt(-1.0));
var_dump(MathCalls::log(1.0), MathCalls::log(0.0));
var_dump(MathCalls::pow(2.0, 10.0), MathCalls::pow(0.0, 2.0), MathCalls::pow(2.0, -1.0));
var_dump(MathCalls::max(2, 7), MathCalls::max(-3, -9), MathCalls::min(2, 7), MathCalls::min(-3, -9));
var_dump(MathCalls::maxOf(0, [900, 2900]), MathCalls::maxOf(0, []), MathCalls::maxOf(0, [-5]), MathCalls::maxOfNone(0));
var_dump(MathCalls::minOf(1000, [900, 2900]), MathCalls::minOf(0, []), MathCalls::minOf(0, [-5]));
var_dump(MathCalls::maxOne(4), MathCalls::minOne(-4));

foreach ([[0.0, -1.0], [-0.0, -2.0], [0.0, -INF]] as [$base, $exponent]) {
    try {
        MathCalls::pow($base, $exponent);
    } catch (DivisionByZeroError $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}
?>
--EXPECT--
float(4)
float(NAN)
float(0)
float(-INF)
float(1024)
float(0)
float(0.5)
int(7)
int(-3)
int(2)
int(-9)
int(2900)
int(0)
int(0)
int(0)
int(900)
int(0)
int(-5)
int(4)
int(-4)
DivisionByZeroError: Math.pow: a base of 0 with a negative exponent divides by zero
DivisionByZeroError: Math.pow: a base of 0 with a negative exponent divides by zero
DivisionByZeroError: Math.pow: a base of 0 with a negative exponent divides by zero
