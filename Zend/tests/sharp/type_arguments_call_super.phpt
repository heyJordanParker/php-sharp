--TEST--
A generic super call gives the method its type arguments when the parent between is a plain PHP class that inherits the PHP# method
--FILE--
<?php
require __DIR__ . '/type_arguments_calls.inc';

use App\Order;
use Calls\OrderPicker;

echo shown((new OrderPicker())->pickOrder(new Order(1))), "\n";
?>
--EXPECT--
App\Box<App.Order>
