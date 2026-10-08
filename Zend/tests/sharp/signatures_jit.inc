<?php

require __DIR__ . '/Signatures.sharp';

for ($round = 0; $round < 100; $round++) {
    $values = [$round, 2, 3];
    $made = new Demo\Signatures("none", ...$values);
    $results = [
        Demo\Signatures::sum(1, 2, 3),
        Demo\Signatures::sum(...$values),
        Demo\Signatures::spread(2, 7, 3),
        Demo\Signatures::spread(...$values),
        Demo\Signatures::made(4, 5),
        Demo\Signatures::made(...$values),
        Demo\Signatures::made(),
        $made->label(7),
        $made->label("seven"),
        $made->isSame($made),
        $made->isSame($round + 5),
        $made->doubled($round, 2),
    ];
}

var_dump(...$results);

try {
    Demo\Signatures::sum(1, "two");
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}

try {
    $made->label([]);
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}

try {
    $made->isSame("nine");
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}

try {
    $made->doubled(1, 2, 3);
} catch (Error $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
