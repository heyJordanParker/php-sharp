--TEST--
A PHP# class calls every method of Sharp.Math.Math, which call the PHP functions of their own names, and Math.pow throws DivisionByZeroError for a base of 0 and a negative exponent
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Math/Math.sharp';
require __DIR__ . '/MathCalls.sharp';

use Demo\MathCalls;

var_dump(MathCalls::ceil(1.2), MathCalls::floor(-1.5), MathCalls::sqrt(16.0), MathCalls::sqrt(-1.0));
var_dump(MathCalls::log(1.0), MathCalls::log(0.0));
var_dump(MathCalls::round(2.5), MathCalls::roundTo(2.567, 2), MathCalls::roundTo(1234.0, -2));
var_dump(MathCalls::pow(2.0, 10.0), MathCalls::pow(0.0, 2.0), MathCalls::pow(2.0, -1.0));

foreach ([[0.0, -1.0], [-0.0, -2.0], [0.0, -INF]] as [$base, $exponent]) {
    try {
        MathCalls::pow($base, $exponent);
    } catch (DivisionByZeroError $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}
?>
--EXPECT--
float(2)
float(-2)
float(4)
float(NAN)
float(0)
float(-INF)
float(3)
float(2.57)
float(1200)
float(1024)
float(0)
float(0.5)
DivisionByZeroError: Math.pow: a base of 0 with a negative exponent divides by zero
DivisionByZeroError: Math.pow: a base of 0 with a negative exponent divides by zero
DivisionByZeroError: Math.pow: a base of 0 with a negative exponent divides by zero
