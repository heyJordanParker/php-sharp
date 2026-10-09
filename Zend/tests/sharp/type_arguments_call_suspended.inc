<?php
require __DIR__ . '/type_arguments_calls.inc';
require __DIR__ . '/harness/Inspector.inc';

// Calls\Repository::suspended suspends the Fiber after it sent the first argument of a generic call. A hot function's
// second call runs the code the tracing JIT compiled on its first.
foreach ([1, 2] as $round) {
    $fiber = new Fiber(fn () => Calls\Repository::suspended(new App\Order($round)));
    $fiber->start();
    echo $fiber->isSuspended() ? 'suspended' : 'returned', "\n";
    $fiber->resume();
    echo shown($fiber->getReturn()), "\n";
}
