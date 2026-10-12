<?php

namespace {
    use Demo\Route;
    use Demo\Spot;
    use Demo\StructKeys;
    use Demo\Tone;
    use Lib\Standing;

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
        attempt(fn () => array_keys($routes));
        // A key reads back as the struct it was, nested struct, enums, float, Map and string included.
        attempt(fn () => StructKeys::keys($routes));
        $output = ob_get_clean();
    }

    echo $output;

    // Sharp\Map::readKey is the exact inverse of Sharp\Map::key, for every value kind a struct holds.
    foreach ([0.1, 1.0, 1e25, -0.0, INF, -INF, NAN, 123456789.125] as $length) {
        attempt(function () use ($length) {
            $text = Sharp\Map::key(new Route([], Standing::Closed, Tone::Cool, $length, [], "\x01", false));
            return [$text, Sharp\Map::readKey($text)->length, Sharp\Map::key(Sharp\Map::readKey($text)) === $text];
        });
    }
    attempt(fn () => Sharp\Set::from([new Spot(1, 2), new Spot(1, 2)]));

    // Plain PHP can hand readKey any text, and readKey builds only a struct.
    attempt(fn () => Sharp\Map::readKey('stdClass{}'));
    attempt(fn () => Sharp\Map::readKey('Demo\Missing{"x":1}'));
    attempt(fn () => Sharp\Map::readKey('Demo\Spot{"x":"one","y":2}'));
    attempt(fn () => Sharp\Map::readKey('Demo\Spot{"x":1}'));
    attempt(fn () => Sharp\Map::readKey('Demo\Spot{"y":2,"x":1}'));
    attempt(fn () => Sharp\Map::readKey('Demo\Spot{"x":1,"y":2}!'));
    attempt(fn () => Sharp\Map::readKey('Demo\Route{"stops":[],"standing":Lib\Standing::Gone,"tone":Demo\Tone::Warm,"length":1.0,"tolls":[],"note":null,"open":true}'));
    attempt(fn () => Sharp\Map::readKey('Demo\Route{"stops":' . str_repeat('[', 600) . str_repeat(']', 600) . '}'));
    attempt(fn () => Sharp\Map::readKey('Demo\Route{"stops":[],"standing":stdClass::Active}'));
    attempt(fn () => Sharp\Map::readKey('Demo\Spot{"x":99999999999999999999,"y":2}'));
    attempt(fn () => Sharp\Map::readKey('Demo\Boxed{"value":"\q"}'));
    attempt(fn () => Sharp\Map::readKey('{"x":1,"y":2}'));
    attempt(fn () => Sharp\Map::key(new stdClass()));
    attempt(fn () => Sharp\Set::from([new stdClass()]));

    // A struct that plain PHP fills with what PHP# keeps out of a key throws where its text is written.
    attempt(fn () => Sharp\Map::key(new Demo\Boxed([new stdClass()])));
    attempt(fn () => Sharp\Map::key(new Demo\Boxed(array_reduce(range(1, 600), fn ($nested) => [$nested], []))));
    attempt(fn () => Sharp\Map::key((new ReflectionClass(Spot::class))->newInstanceWithoutConstructor()));
}
