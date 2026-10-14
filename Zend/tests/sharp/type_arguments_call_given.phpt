--TEST--
A generic call gives the method the type arguments it writes or the checker infers, static or not, through a method value, one a generic method makes from its own, a spread, a plain PHP @template interface, a parent or a super call, and a method passes its own and its class's on to the next generic call
--FILE--
<?php
require __DIR__ . '/type_arguments_calls.inc';
require __DIR__ . '/harness/Inspector.inc';

use App\Order;
use Calls\OrderPicker;
use Calls\Repository;
use Calls\SharpPlainWrapper;
use Calls\SharpWrapper;
use Calls\Shelf;

// A hot function's second call runs the code the tracing JIT compiled on its first.
$order = new Order(1);
foreach ([1, 2] as $round) {
    echo shown(Repository::written($order)), "\n";
    echo shown(Repository::inferred($order)), "\n";
    echo shown((new Shelf())->stock($order)), "\n";
    echo shown((new Shelf())->made($order)), "\n";
    echo shown(Repository::boxedByValue($order)), "\n";
    echo implode(', ', (new Shelf())->taken([Shelf::orders(), Shelf::orders()])), "\n";
    echo shown(Repository::forwarded($order)), "\n";
    echo shown(Repository::spread([$order, new Order(2)])), "\n";
    echo shown(Repository::keyed($order)), "\n";
    echo shown(Repository::numbered()->twice()), "\n";
    echo shown(Repository::wrapped(new SharpWrapper(), $order)), "\n";
    echo shown(Repository::wrappedByParent(new SharpPlainWrapper(), $order)), "\n";
    echo shown((new OrderPicker())->pickOrder($order)), "\n";
}
?>
--EXPECT--
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
1, 1
App\Box<App.Order>
App\Box<App.Order>
App\Pair<int, App.Order>
App\Pair<int, int>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
1, 1
App\Box<App.Order>
App\Box<App.Order>
App\Pair<int, App.Order>
App\Pair<int, int>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
