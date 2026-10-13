<?php

namespace {
    use Demo\Spot;
    use Demo\StructKeys;

    require __DIR__ . '/attempt.inc';
    require __DIR__ . '/harness/Standing.inc';
    require __DIR__ . '/Tone.sharp';
    require __DIR__ . '/Spot.sharp';
    require __DIR__ . '/Route.sharp';
    require __DIR__ . '/StructKeys.sharp';
    require __DIR__ . '/Boxed.sharp';

    // Each round runs every method, so the JIT variants compile them before the last round, whose output is printed.
    for ($round = 0; $round < 100; $round++) {
        ob_start();
        // Two equal structs are one key, in an index write, a literal and a Set, and a loop reads each key back.
        $counts = StructKeys::counts();
        attempt(fn () => $counts);
        attempt(fn () => StructKeys::read($counts));
        attempt(fn () => StructKeys::literal());
        attempt(fn () => StructKeys::spots());
        // -0.0 and 0.0 are one key, and so are two Maps that == calls equal, in any order.
        $routes = StructKeys::routes(
            StructKeys::route(0.0, ['b' => 2, 'a' => 1, '7' => 3]),
            StructKeys::route(-0.0, ['a' => 1, '7' => 3, 'b' => 2]),
        );
        attempt(fn () => [count($routes), $routes->values()]);
        // A key reads back as the struct it was, nested struct, enums, float, Map and string included.
        attempt(fn () => StructKeys::keys($routes));
        $output = ob_get_clean();
    }

    echo $output;

    // Plain PHP's struct joins a Set by its value too, and a struct holding an object joins by that object.
    attempt(fn () => new Sharp\Set([new Spot(1, 2), new Spot(1, 2)]));
    $held = new stdClass();
    attempt(fn () => count(new Sharp\Set([new Demo\Boxed([$held]), new Demo\Boxed([$held]), new Demo\Boxed([new stdClass()])])));
}
