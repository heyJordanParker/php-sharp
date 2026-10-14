<?php

require __DIR__ . '/harness/Shapes.inc';
require __DIR__ . '/Leave.sharp';

for ($round = 0; $round < 100; $round++) {
    $leave = new Demo\Leave();
    $results = [
        $leave->price(['pro' => 5], 'pro'),
        $leave->price(['pro' => 5], 'basic'),
        $leave->finishedCount(),
        $leave->label(['pro' => 'Pro'], 'pro'),
        $leave->label(['pro' => 'Pro'], 'basic'),
        $leave->circle(2.0)?->radius,
        $leave->circle(null),
        $leave->total([1, null, 3]),
        $leave->upTo([1, 2, null, 4]),
        $leave->finishedCount(),
        $leave->recovered(['pro' => 5], 'pro'),
        $leave->recovered(['pro' => 5], 'basic'),
        $leave->nested(['pro' => 5, 'basic' => 1], 'pro', 'basic'),
        $leave->nested(['basic' => 1], 'pro', 'basic'),
        $leave->nested([], 'pro', 'basic'),
    ];
}

var_dump(...$results);
