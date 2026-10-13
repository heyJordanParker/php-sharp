<?php

namespace {
    use Lib\Code;
    use Lib\Size;
    use Lib\Standing;

    require __DIR__ . '/attempt.inc';
    require __DIR__ . '/harness/Standing.inc';
    require __DIR__ . '/EnumKeys.sharp';

    for ($round = 0; $round < 100; $round++) {
        ob_start();
        $headcount = new Demo\Headcount();
        $headcount->add(Standing::Active);
        attempt(fn () => $headcount->add(Standing::Active));
        attempt(fn () => $headcount->seededCounts());
        attempt(fn () => Demo\Headcount::count(['closed' => 2], Standing::Closed));
        attempt(fn () => Demo\Headcount::count([], Standing::Active));
        attempt(fn () => Demo\Headcount::ensure(['closed' => 2], Standing::Closed));
        attempt(fn () => Demo\Headcount::ensure([], Standing::Active));
        attempt(fn () => Demo\Headcount::sizes(Size::Small, Size::Large));
        attempt(fn () => Demo\Headcount::codes(Code::Five));
        attempt(fn () => Demo\Headcount::lookup(['active' => 3], Standing::Active));
        attempt(fn () => Demo\Headcount::lookup(['active' => 3], Standing::Closed));
        attempt(fn () => Demo\Headcount::without(['active' => 3, 'closed' => 4], Standing::Active));
        attempt(fn () => Demo\Headcount::nest(Standing::Closed, Size::Large));
        attempt(fn () => Demo\Headcount::byName(['active' => 3, 'closed' => 4]));
        // A key that is no case fails the Map's check where plain PHP hands it in.
        attempt(fn () => Demo\Headcount::byName(['missing' => 1]));
        // PHP stores the key "5" as the int 5, and PHP# reads it back as the string "5".
        attempt(fn () => Demo\Headcount::skus(['5' => 1, 'tea' => 2, 'pie' => 0]));
        // groupBy keyed by a backed enum: plain PHP sees the backing values, and a loop reads back the cases.
        attempt(fn () => Demo\Headcount::grouped([1, 2, 3]));
        attempt(fn () => Demo\Headcount::groupNames([1, 2, 3]));
        $output = ob_get_clean();
    }

    echo $output;

    // Plain PHP keeps refusing an enum as a key.
    attempt(function () {
        $counts = [];
        $counts[Standing::Active] = 1;
        return $counts;
    });
    attempt(fn () => ['active' => 1][Standing::Active] ?? 0);
    attempt(fn () => [Size::Small => 'small']);
    attempt(fn () => (new class { public array $seeded = [Standing::Active => 0]; })->seeded);
}
