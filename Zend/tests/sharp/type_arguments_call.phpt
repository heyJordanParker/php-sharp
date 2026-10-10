--TEST--
A generic call gives the method the type arguments it writes or the checker infers, static or not, through a spread and across a Fiber suspension, and a method passes its own and its class's on to the next generic call
--FILE--
<?php
require __DIR__ . '/type_arguments_call.inc';
?>
--EXPECT--
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Pair<int, App.Order>
App\Pair<int, int>
suspended
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Pair<int, App.Order>
App\Pair<int, int>
suspended
App\Box<App.Order>
