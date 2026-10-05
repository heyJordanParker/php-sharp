--TEST--
PHP# arithmetic throws on overflow while the same PHP arithmetic in the same request gives a float
--FILE--
<?php

require __DIR__ . '/attempt.inc';
require __DIR__ . '/Overflow.sharp';

final class PhpOverflow
{
    public static function add(int $a, int $b) { return $a + $b; }
    public static function sub(int $a, int $b) { return $a - $b; }
    public static function mul(int $a, int $b) { return $a * $b; }
    public static function negate(int $a) { return -$a; }
    public static function maxPlusOne() { return PHP_INT_MAX + 1; }
    public static function addAssign(int $a, int $b) { $x = $a; $x += $b; return $x; }
    public static function preInc(int $a) { $x = $a; return ++$x; }
    public static function postInc(int $a) { $x = $a; $x++; return $x; }
    public static function preDec(int $a) { $x = $a; return --$x; }
    public static function postDec(int $a) { $x = $a; $x--; return $x; }
}

foreach ([
    'add' => [PHP_INT_MAX, 1],
    'sub' => [PHP_INT_MIN, 1],
    'mul' => [PHP_INT_MAX, 2],
    'negate' => [PHP_INT_MIN],
    'maxPlusOne' => [],
    'addAssign' => [PHP_INT_MAX, 1],
    'preInc' => [PHP_INT_MAX],
    'postInc' => [PHP_INT_MAX],
    'preDec' => [PHP_INT_MIN],
    'postDec' => [PHP_INT_MIN],
] as $method => $arguments) {
    echo $method, "\n";
    attempt(fn () => PhpOverflow::$method(...$arguments));
    attempt(fn () => Demo\Overflow::$method(...$arguments));
}
?>
--EXPECT--
add
float(9.223372036854776E+18)
ArithmeticError: Integer overflow in Overflow.sharp on line 7
sub
float(-9.223372036854776E+18)
ArithmeticError: Integer overflow in Overflow.sharp on line 12
mul
float(1.8446744073709552E+19)
ArithmeticError: Integer overflow in Overflow.sharp on line 17
negate
float(9.223372036854776E+18)
ArithmeticError: Integer overflow in Overflow.sharp on line 22
maxPlusOne
float(9.223372036854776E+18)
ArithmeticError: Integer overflow in Overflow.sharp on line 27
addAssign
float(9.223372036854776E+18)
ArithmeticError: Integer overflow in Overflow.sharp on line 48
preInc
float(9.223372036854776E+18)
ArithmeticError: Integer overflow in Overflow.sharp on line 69
postInc
float(9.223372036854776E+18)
ArithmeticError: Integer overflow in Overflow.sharp on line 75
preDec
float(-9.223372036854776E+18)
ArithmeticError: Integer overflow in Overflow.sharp on line 88
postDec
float(-9.223372036854776E+18)
ArithmeticError: Integer overflow in Overflow.sharp on line 94
