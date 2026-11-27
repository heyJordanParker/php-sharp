--TEST--
A PHP# class calls the math members of Kotlin, Rust and .NET that PHP# lacked: Math.log10, Math.exp, the trigonometric functions, Math.PI and Math.E, isNaN, checked and saturating int arithmetic at both ends of the range, and Int.parse in another base, with its ok() and each edge case
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Demo\MathQueries;

function floats(array $values): string
{
    return implode(' ', array_map(fn (float $value) => sprintf('%.6f', $value), $values));
}

function refused(Closure $call): void
{
    try {
        $call();
    } catch (ValueError|ArithmeticError|LogicException $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}

echo floats(MathQueries::logs(1.0)), ' | ', floats(MathQueries::logs(-1.0)), ' | ', floats(MathQueries::logs(0.0)), "\n";
echo floats(MathQueries::trigonometry(0.5)), ' | ', floats(MathQueries::trigonometry(2.0)), "\n";
echo floats([MathQueries::angle(1.0, 1.0), MathQueries::angle(0.0, -1.0)]), ' | ', floats(MathQueries::constants()), "\n";
var_dump(MathQueries::isNaN(NAN), MathQueries::isNaN(1.0), MathQueries::isNaN(INF));

$power = 4611686018427387904;
foreach ([[3, 4], [PHP_INT_MAX, 1], [PHP_INT_MIN, 1], [PHP_INT_MIN, -1], [$power, 2], [-$power, 2], [$power, -2], [3, -3074457345618258603], [3, -3074457345618258602]] as [$a, $b]) {
    echo $a, ' ', $b, ': ', json_encode(MathQueries::checked($a, $b)), ' ', json_encode(MathQueries::saturating($a, $b)), "\n";
}

echo MathQueries::parsed('ff', 16), ' ', MathQueries::parsed('-FF', 16), ' ', MathQueries::parsed('+0101', 2), ' ', MathQueries::parsed('z', 36), ' ';
echo MathQueries::parsed('-8000000000000000', 16), ' ', MathQueries::parsed('9223372036854775807', 10), ' ', MathQueries::parsed('-0', 8), "\n";
var_dump(MathQueries::tryParse('7fffffffffffffff', 16), MathQueries::tryParse('8000000000000000', 16), MathQueries::tryParse('g', 16), MathQueries::tryParse('', 16));
refused(fn () => MathQueries::parsed('8000000000000000', 16));
refused(fn () => MathQueries::parsed('19', 8));
refused(fn () => MathQueries::parsed(' ff', 16));
refused(fn () => MathQueries::parsed('9223372036854775808', 10));
refused(fn () => MathQueries::parsed('12', 1));
refused(fn () => MathQueries::tryParse('12', 37));
?>
--EXPECT--
0.000000 2.718282 | NaN 0.367879 | -INF 1.000000
0.479426 0.877583 0.546302 0.523599 1.047198 0.463648 | 0.909297 -0.416147 -2.185040 NaN NaN 1.107149
0.785398 3.141593 | 3.141593 2.718282
bool(true)
bool(false)
bool(false)
3 4: [7,-1,12] [7,-1,12]
9223372036854775807 1: [null,9223372036854775806,9223372036854775807] [9223372036854775807,9223372036854775806,9223372036854775807]
-9223372036854775808 1: [-9223372036854775807,null,-9223372036854775808] [-9223372036854775807,-9223372036854775808,-9223372036854775808]
-9223372036854775808 -1: [null,-9223372036854775807,null] [-9223372036854775808,-9223372036854775807,9223372036854775807]
4611686018427387904 2: [4611686018427387906,4611686018427387902,null] [4611686018427387906,4611686018427387902,9223372036854775807]
-4611686018427387904 2: [-4611686018427387902,-4611686018427387906,-9223372036854775808] [-4611686018427387902,-4611686018427387906,-9223372036854775808]
4611686018427387904 -2: [4611686018427387902,4611686018427387906,-9223372036854775808] [4611686018427387902,4611686018427387906,-9223372036854775808]
3 -3074457345618258603: [-3074457345618258600,3074457345618258606,null] [-3074457345618258600,3074457345618258606,-9223372036854775808]
3 -3074457345618258602: [-3074457345618258599,3074457345618258605,-9223372036854775806] [-3074457345618258599,3074457345618258605,-9223372036854775806]
255 -255 5 35 -9223372036854775808 9223372036854775807 0
int(9223372036854775807)
NULL
NULL
NULL
LogicException: Int.parse: "8000000000000000" is out of range for an int
LogicException: Int.parse: "19" is not an int in base 8
LogicException: Int.parse: " ff" is not an int in base 16: remove its spaces with trim() first
LogicException: Int.parse: "9223372036854775808" is out of range for an int
ValueError: Int.parse takes a base from 2 to 36, and 1 is not one.
ValueError: Int.parse takes a base from 2 to 36, and 37 is not one.
