--TEST--
A generic call keeps the type arguments it gives its method across a Fiber suspension
--FILE--
<?php
require __DIR__ . '/type_arguments_calls.inc';
require __DIR__ . '/harness/Inspector.inc';

use App\Order;
use Calls\Repository;

// Calls\Repository::suspended suspends the Fiber after it sent the first argument of a generic call. A hot function's
// second call runs the code the tracing JIT compiled on its first.
foreach ([1, 2] as $round) {
    $fiber = new Fiber(fn () => Repository::suspended(new Order($round)));
    $fiber->start();
    echo $fiber->isSuspended() ? 'suspended' : 'returned', "\n";
    $fiber->resume();
    echo shown($fiber->getReturn()), "\n";
}
?>
--EXPECT--
suspended
App\Box<App.Order>
suspended
App\Box<App.Order>
