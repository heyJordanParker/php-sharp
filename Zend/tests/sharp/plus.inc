<?php

require __DIR__ . '/Plus.sharp';

for ($round = 0; $round < 100; $round++) {
    $plus = new Demo\Plus();
    $plus->record('a');
    $results = [
        Demo\Plus::join('Ada', 'Lovelace'),
        Demo\Plus::digits('1', '2'),
        Demo\Plus::folded(),
        Demo\Plus::append('done'),
        $plus->record('b'),
        Demo\Plus::length('ab', 'cde'),
        Demo\Plus::repeat('ab', 3),
        Demo\Plus::add(1, 2),
    ];
}

var_dump(...$results);
