<?php

namespace {
    use Demo\Stamp;
    use Demo\Structs;

    require __DIR__ . '/attempt.inc';
    require __DIR__ . '/Stamp.sharp';
    require __DIR__ . '/Structs.sharp';

    // Each round runs every method, so the JIT variants compile them before the last round, whose output is printed.
    for ($round = 0; $round < 100; $round++) {
        ob_start();
        // A changed copy leaves the original, and with runs the init body of the property it sets.
        $stamp = Structs::stamp('post', 1);
        $heavier = $stamp->heavier();
        $renamed = Structs::renamed($stamp, '  first  ');
        attempt(fn () => [$stamp->weight, $heavier->weight, $stamp->shown, $renamed->shown, $renamed->weight]);
        // An object initializer from another class runs the init body too.
        attempt(fn () => Structs::shown('post', '  card  ')->shown);
        // == compares by value, and a struct equals no null and no value of another type.
        attempt(fn () => [
            Structs::same($stamp, Structs::stamp('post', 1)),
            Structs::same($stamp, $heavier),
            Structs::sameOrNull($stamp, null),
            Structs::sameAsAny($stamp, 'post'),
            Structs::sameAsAny($stamp, Structs::stamp('post', 1)),
        ]);
        // contains finds an equal struct, and two Lists and two Maps of equal structs are equal, a Map in any order.
        attempt(fn () => [
            Structs::holds([$heavier, Structs::stamp('post', 1)], $stamp),
            Structs::holds([$heavier], $stamp),
            Structs::sameLists([$stamp, $heavier], [Structs::stamp('post', 1), $stamp->heavier()]),
            Structs::sameLists([$stamp, $heavier], [$heavier, $stamp]),
            Structs::sameMaps(['a' => $stamp, 'b' => $heavier], ['b' => $stamp->heavier(), 'a' => Structs::stamp('post', 1)]),
            Structs::sameMaps(['a' => $stamp], ['a' => $heavier]),
        ]);
        $output = ob_get_clean();
    }

    echo $output;

    // Plain PHP can't change a struct: a write and clone with values throw.
    attempt(function () use ($stamp) { $stamp->name = 'x'; });
    attempt(function () use ($stamp) { $stamp->shown = 'x'; });
    attempt(fn () => clone($stamp, ['name' => 'x']));
    // Nor inherit one: a struct is a final class.
    eval('class Sticker extends Demo\Stamp {}');
}
