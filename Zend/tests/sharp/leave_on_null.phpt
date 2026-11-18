--TEST--
PHP# `a ?? return`, `a ?? continue` and `a ?? break` leave from the middle of an expression when a is null
--FILE--
<?php

require __DIR__ . '/harness/Shapes.inc';
require __DIR__ . '/vendor/autoload.php';

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
        $leave->counted([[1, 2, null, 4], [5, null]]),
        $leave->skipped([1, null, 2]),
        $leave->stepped([1, 2, null]),
        $leave->after([5, 1, 2, null, 7]),
    ];
}

var_dump(...$results);
?>
--EXPECT--
int(10)
int(-1)
int(2)
string(12) "<pro> is Pro"
string(18) "<basic> is missing"
float(2)
NULL
int(8)
int(6)
int(5)
int(10)
int(-2)
int(10)
int(2)
int(-3)
int(203)
int(2)
int(3)
int(3)
