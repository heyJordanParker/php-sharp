<?php

namespace {
    use Lib\Code;
    use Lib\Size;
    use Lib\Status;

    require __DIR__ . '/attempt.inc';
    require __DIR__ . '/harness/Status.inc';
    require __DIR__ . '/EnumKeys.sharp';

    for ($round = 0; $round < 100; $round++) {
        ob_start();
        $tally = new Demo\Tally();
        $tally->add(Status::Active);
        attempt(fn () => $tally->add(Status::Active));
        attempt(fn () => $tally->seeded());
        attempt(fn () => Demo\Tally::count(['closed' => 2], Status::Closed));
        attempt(fn () => Demo\Tally::count([], Status::Active));
        attempt(fn () => Demo\Tally::ensure(['closed' => 2], Status::Closed));
        attempt(fn () => Demo\Tally::ensure([], Status::Active));
        attempt(fn () => Demo\Tally::sizes(Size::Small, Size::Large));
        attempt(fn () => Demo\Tally::codes(Code::Five));
        attempt(fn () => Demo\Tally::read(['active' => 3], Status::Active));
        attempt(fn () => Demo\Tally::read(['active' => 3], Status::Closed));
        attempt(fn () => Demo\Tally::lookup(['active' => 3], Status::Active));
        attempt(fn () => Demo\Tally::lookup(['active' => 3], Status::Closed));
        attempt(fn () => Demo\Tally::without(['active' => 3, 'closed' => 4], Status::Active));
        attempt(fn () => Demo\Tally::nest(Status::Closed, Size::Large));
        $output = ob_get_clean();
    }

    echo $output;

    // Plain PHP keeps refusing an enum as a key.
    attempt(function () {
        $counts = [];
        $counts[Status::Active] = 1;
        return $counts;
    });
    attempt(fn () => ['active' => 1][Status::Active] ?? 0);
    attempt(fn () => [Size::Small => 'small']);
    attempt(fn () => (new class { public array $seeded = [Status::Active => 0]; })->seeded);
}
