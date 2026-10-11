<?php

namespace Demo;

require __DIR__ . '/attempt.inc';
require __DIR__ . '/harness/Overflow.inc';
require __DIR__ . '/Overflow.sharp';

function phpPower(int $a, int $b)
{
    return $a ** $b;
}

function powCases(): void
{
    attempt(fn () => Overflow::power(2, 62));
    attempt(fn () => Overflow::power(2, 63));
    attempt(fn () => Overflow::power(-2, 63));
    attempt(fn () => Overflow::power(-2, 64));
    attempt(fn () => Overflow::maxSquared());
    attempt(fn () => Overflow::powAssign(3037000499));
    attempt(fn () => Overflow::powAssign(3037000500));

    attempt(fn () => Overflow::power(2, -1));
    attempt(fn () => Overflow::power(0, -1));
    attempt(fn () => Overflow::constantNegativePower());
    attempt(fn () => Overflow::zeroNegativePower());
    attempt(fn () => Overflow::inverse(2, -1));
    attempt(fn () => Overflow::half());
    attempt(fn () => Overflow::floatExponent(4, -1.0));
    attempt(fn () => phpPower(2, -1));
    attempt(fn () => 2 ** -1);

    attempt(fn () => Overflow::powProperty(new Cell(3037000499), 2));
    $counter = new Cell(3037000500);
    attempt(fn () => Overflow::powProperty($counter, 2));
    var_dump($counter->value);
    attempt(fn () => Overflow::powLoose(new LooseCounter(3037000500), 2));
    $counter = new Cell(2);
    attempt(fn () => Overflow::powProperty($counter, -1));
    var_dump($counter->value);
    attempt(fn () => Overflow::powLoose(new LooseCounter(2), -1));

    attempt(fn () => Overflow::deadPower(2, 62));
    attempt(fn () => Overflow::deadPower(2, 63));
    attempt(fn () => Overflow::deadPower(2, -1));
    attempt(fn () => Overflow::deadPowAssign(2, -1));
}
