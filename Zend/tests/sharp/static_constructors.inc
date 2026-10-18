<?php

use StaticConstructors\Flaky;
use StaticConstructors\Journal;
use StaticConstructors\Pools;
use StaticConstructors\Rates;
use StaticConstructors\Registry;

function used(string $label, callable $use): void
{
    try {
        $value = json_encode($use());
    } catch (Throwable $e) {
        $previous = $e->getPrevious();
        $value = get_class($e) . ': ' . $e->getMessage()
            . ($previous ? ' (previous ' . get_class($previous) . ': ' . $previous->getMessage() . ')' : '');
    }
    echo $label, ': ', $value, "\n";
}

used('a static method that uses no static', fn () => Rates::hello());
used('no static is used yet, so nothing ran', fn () => Journal::$lines);
used('the first use of a static', fn () => [Rates::of('EUR'), Rates::of('GBP'), Rates::of('USD')]);
used('initial values, then the static constructor', fn () => Journal::$lines);
used('it ran once', fn () => [Rates::of('EUR'), Rates::$runs]);
used('setup that fills no static', fn () => [Registry::count(), Journal::$lines]);
for ($use = 1; $use <= 3; $use++) {
    used("use $use of a class whose static constructor threw", fn () => Flaky::touch());
}
used('a write after it threw', fn () => Flaky::$hits = 5);
used('each type argument runs it once', fn () => [Pools::ofInt(), Pools::ofString(), Pools::ofInt(), Journal::$lines]);
used('a type argument whose static constructor threw', fn () => Pools::ofFloat());
used('a later use of it', fn () => Pools::ofFloat());
used('another type argument still runs', fn () => Pools::ofInt());
