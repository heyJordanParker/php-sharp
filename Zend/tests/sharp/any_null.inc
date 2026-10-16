<?php

require __DIR__ . '/NonNull.sharp';

use Strict\NonNull;

$calls = [
    'keep 1' => fn () => NonNull::keep(1),
    'keep null' => fn () => NonNull::keep(null),
    'count [1, "a"]' => fn () => NonNull::count([1, 'a']),
    'count [1, null]' => fn () => NonNull::count([1, null]),
    'size [a: 1]' => fn () => NonNull::size(new Sharp\Map(['a', 1])),
    'size [a: null]' => fn () => NonNull::size(new Sharp\Map(['a', null])),
    'loose null' => fn () => NonNull::loose(null),
    'set 2' => function () { $holder = new NonNull(); $holder->value = 2; return $holder->value; },
    'set null' => function () { $holder = new NonNull(); $holder->value = null; return $holder->value; },
];
foreach ($calls as $name => $call) {
    try {
        $result = var_export($call(), true);
    } catch (TypeError $error) {
        $result = $error->getMessage();
    }
    echo $name, ': ', $result, "\n";
}
