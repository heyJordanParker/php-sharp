--TEST--
PHP# throws ArithmeticError when ++, -- or += overflows a static property
--FILE--
<?php

require __DIR__ . '/attempt.inc';
require __DIR__ . '/vendor/autoload.php';

attempt(fn () => Demo\Tally::postInc(PHP_INT_MAX));
var_dump(Demo\Tally::$count);
attempt(fn () => Demo\Tally::preInc(PHP_INT_MAX));
attempt(fn () => Demo\Tally::postDec(PHP_INT_MIN));
attempt(fn () => Demo\Tally::preDec(PHP_INT_MIN));
var_dump(Demo\Tally::$count);
attempt(fn () => Demo\Tally::add(PHP_INT_MAX, 1));
attempt(fn () => Demo\Tally::add(1, 2));
attempt(fn () => Demo\Tally::postInc(1));
attempt(fn () => Demo\Tally::postDec(1));

$thrown = 0;
for ($i = 0; $i < 50; $i++) {
    try {
        Demo\Tally::postInc($i < 25 ? 1 : PHP_INT_MAX);
        Demo\Tally::preDec($i < 25 ? 1 : PHP_INT_MIN);
    } catch (ArithmeticError) {
        $thrown++;
    }
}
echo "thrown $thrown of 50\n";
?>
--EXPECT--
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
