<?php

namespace Demo;

class Counter
{
    public function __construct(public int $value) {}
}

final class LooseCounter
{
    public $value;

    public function __construct($value)
    {
        $this->value = $value;
    }
}

require __DIR__ . '/attempt.inc';
require __DIR__ . '/Overflow.sharp';

function powCases(): void
{
    attempt(fn () => Overflow::power(2, 62));
    attempt(fn () => Overflow::power(2, 63));
    attempt(fn () => Overflow::power(-2, 63));
    attempt(fn () => Overflow::power(-2, 64));
    attempt(fn () => Overflow::maxSquared());
    attempt(fn () => Overflow::powAssign(3037000499));
    attempt(fn () => Overflow::powAssign(3037000500));
    attempt(fn () => Overflow::inverse(2, -1));
    attempt(fn () => Overflow::half());

    attempt(fn () => Overflow::powProperty(new Counter(3037000499), 2));
    $counter = new Counter(3037000500);
    attempt(fn () => Overflow::powProperty($counter, 2));
    var_dump($counter->value);
    attempt(fn () => Overflow::powLoose(new LooseCounter(3037000500), 2));

    attempt(fn () => Overflow::deadPower(2, 62));
    attempt(fn () => Overflow::deadPower(2, 63));
}
