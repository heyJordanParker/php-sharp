--TEST--
PHP# throws ArithmeticError when a parameter default overflows
--FILE--
<?php

require __DIR__ . '/attempt.inc';
require __DIR__ . '/Overflow.sharp';

attempt(fn () => Demo\Overflow::withDefault(5));
attempt(fn () => Demo\Overflow::withDefault());
echo (new ReflectionMethod(Demo\Overflow::class, 'withDefault'))->getParameters()[0], "\n";
?>
--EXPECT--
int(5)
ArithmeticError: Integer overflow in Overflow.sharp on line 121
Parameter #0 [ <optional> int $a = Demo\PHP_INT_MAX + 1 ]
