--TEST--
A generic call gives the method the type arguments it writes or the checker infers, static or not and through a spread, and a method passes its own and its class's on to the next generic call
--FILE--
<?php
require __DIR__ . '/type_arguments_calls.inc';

use App\Order;
use Calls\Repository;
use Calls\Shelf;

$order = new Order(1);
echo shown(Repository::written($order)), "\n";
echo shown(Repository::inferred($order)), "\n";
echo shown((new Shelf())->stock($order)), "\n";
echo shown(Repository::forwarded($order)), "\n";
echo shown(Repository::spread([$order, new Order(2)])), "\n";
echo shown(Repository::keyed($order)), "\n";
echo shown(Repository::numbered()->twice()), "\n";
?>
--EXPECT--
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Pair<int, App.Order>
App\Pair<int, int>
