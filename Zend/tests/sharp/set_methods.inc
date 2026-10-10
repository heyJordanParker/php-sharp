<?php

namespace {
    require __DIR__ . '/attempt.inc';
    require __DIR__ . '/harness/Standing.inc';
    require __DIR__ . '/Sets.sharp';

    // Each round runs every method, so the JIT variants compile them before the last round, whose output is printed.
    for ($round = 0; $round < 100; $round++) {
        ob_start();
        // Plain PHP passes a list, which each Set parameter makes a Set: duplicates go, the first order stays.
        attempt(fn () => Demo\Tagged::strings(['new', '5', 'new']));
        attempt(fn () => Demo\Tagged::reads(['vip', '5', 'tea']));
        attempt(fn () => Demo\Tagged::missing(['vip']));
        attempt(fn () => Demo\Tagged::ints([5, 2 => 2, 5]));
        attempt(fn () => Demo\Tagged::cases([Lib\Standing::Active]));
        attempt(fn () => Demo\Tagged::strings([1.5]));

        $tagged = new Demo\Tagged([3, 3, 1], ['b', 'a', 'b']);
        attempt(fn () => $tagged->ids);
        attempt(fn () => $tagged->properties(Lib\Standing::Closed));
        attempt(fn () => $tagged->tags);
        attempt(fn () => $tagged->once(['x']));
        attempt(fn () => $tagged->once(null));
        // A get-only promoted Set is set once, to the Set of the list.
        attempt(fn () => $tagged->labels);
        // A lambda, a variadic and a nullable variadic make each list they take a Set, so contains finds an element.
        attempt(fn () => Demo\Tagged::lambdas(['v', 'v', 'x']));
        attempt(fn () => Demo\Tagged::groups(['a', 'b'], ['c']));
        attempt(fn () => Demo\Tagged::maybe(['a'], null, ['b', 'a']));
        $output = ob_get_clean();
    }

    echo $output;

    // Sharp\Set::from keeps each element's first place under its key, and gives a Set back as it is.
    attempt(fn () => Sharp\Set::from(['b', 'a', 'b', '7', 7]));
    $set = Sharp\Set::from([3, 1, 3]);
    attempt(fn () => [$set, Sharp\Set::from($set) === $set]);
    attempt(fn () => Sharp\Set::from([Lib\Standing::Closed, Lib\Standing::Active, Lib\Standing::Closed]));
    attempt(fn () => Sharp\Set::from([Lib\Size::Large, Lib\Code::Five]));
    attempt(fn () => Sharp\Set::from([1 => 'x']));
    attempt(fn () => Sharp\Set::from(['x', null]));
    attempt(fn () => Sharp\Set::from([new stdClass()]));
}
