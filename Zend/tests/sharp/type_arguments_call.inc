<?php
require __DIR__ . '/type_arguments_calls.inc';

use App\Order;
use Calls\Repository;
use Calls\Shelf;

// A hot function's second call runs the code the tracing JIT compiled on its first.
$order = new Order(1);
foreach ([1, 2] as $round) {
    echo shown(Repository::written($order)), "\n";
    echo shown(Repository::inferred($order)), "\n";
    echo shown((new Shelf())->stock($order)), "\n";
    echo shown(Repository::forwarded($order)), "\n";
    echo shown(Repository::spread([$order, new Order(2)])), "\n";
    echo shown(Repository::keyed($order)), "\n";
    echo shown(Repository::numbered()->twice()), "\n";
}
