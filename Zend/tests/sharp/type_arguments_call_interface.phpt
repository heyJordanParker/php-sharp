--TEST--
A generic call through a plain PHP @template interface gives the PHP# method that implements it its type arguments
--FILE--
<?php
require __DIR__ . '/type_arguments_calls.inc';

use App\Order;
use Calls\Repository;
use Calls\SharpWrapper;

// A hot function's second call runs the code the tracing JIT compiled on its first.
foreach ([1, 2] as $round) {
    echo shown(Repository::wrapped(new SharpWrapper(), new Order(1))), "\n";
}
?>
--EXPECT--
App\Box<App.Order>
App\Box<App.Order>
