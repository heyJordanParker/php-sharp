--TEST--
A lambda in a generic method, and a lambda in that lambda, build and call with the method's type arguments after the method returns
--FILE--
<?php
require __DIR__ . '/type_arguments_calls.inc';

use App\Order;
use Calls\Repository;

$order = new Order(1);
echo shown(Repository::orderLater($order)()), "\n";
echo shown(Repository::orderLaterStill($order)()()), "\n";
echo shown(Repository::orderHeld($order)()), "\n";
// Plain PHP gives the method no type arguments, so its lambda builds with the bounds.
echo shown(Repository::later($order)()), "\n";
?>
--EXPECT--
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Box<Any?>
