<?php

namespace {
    require __DIR__ . '/attempt.inc';
    require __DIR__ . '/harness/Probe.inc';
    require __DIR__ . '/harness/Standing.inc';
    require __DIR__ . '/harness/fixture.inc';
    fixture('Sets');

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

    // new Sharp\Set keeps each element once, in its first place, whatever key the array holds it under.
    attempt(fn () => new Sharp\Set(['b', 'a', 'b', '7', 7]));
    attempt(fn () => new Sharp\Set([3, 1, 3]));
    attempt(fn () => new Sharp\Set([Lib\Standing::Closed, Lib\Standing::Active, Lib\Standing::Closed]));
    attempt(fn () => new Sharp\Set([Lib\Size::Large, Lib\Code::Five]));
    attempt(fn () => new Sharp\Set([1 => 'x']));
    attempt(fn () => new Sharp\Set(['x', null]));
    attempt(fn () => new Sharp\Set([new stdClass()]));
}
