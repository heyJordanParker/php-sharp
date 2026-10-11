<?php

namespace {
    require __DIR__ . '/Deadlines.sharp';

    $shown = static fn (array $keys): string => implode(' ', array_map(static fn (int|string|null $key): string => var_export($key, true), $keys));

    for ($round = 0; $round < 100; $round++) {
        ob_start();
        echo $shown(Demo\Deadlines::byDay([3, null, -2, 1, 0])), "\n";
        echo $shown(Demo\Deadlines::byDay([0, 1, -2, null, 3])), "\n";
        echo $shown(Demo\Deadlines::byDay([null, 3, 1, 0, -2])), "\n";
        echo $shown(Demo\Deadlines::byOwner(['b', null, '', 'a', 'B'])), "\n";
        echo $shown(Demo\Deadlines::byOwner(['', 'B', 'a', null, 'b'])), "\n";
        echo $shown(Demo\Deadlines::byDue(['ship' => 3, 'draft' => null, 'review' => -2, 'test' => 1, 'plan' => 0])), "\n";
        echo $shown(Demo\Deadlines::byDue(['plan' => 0, 'test' => 1, 'review' => -2, 'draft' => null, 'ship' => 3])), "\n";
        echo $shown(Demo\Deadlines::bySlot([7, 0, 5, 1, -2, 3])), "\n";
        echo $shown(Demo\Deadlines::bySlot([3, 1, 5, -2, 0, 7])), "\n";
        $output = ob_get_clean();
    }

    echo $output;
}
