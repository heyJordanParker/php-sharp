<?php

require __DIR__ . '/attempt.inc';
require __DIR__ . '/Tally.sharp';

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
