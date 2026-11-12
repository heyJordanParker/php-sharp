--TEST--
phpdbg finds a PHP# class by its exact name, and its methods too, spec section 24: `break ExactNames\Order::Total` stops in `Total` and not in its twin `total`, a breakpoint on `exactnames\order::kind` names no PHP# method, and `break ExactNames\Order::sum#1` resolves in the loaded class at once
--PHPDBG--
b ExactNames\Order::Total
b exactnames\order::kind
r
b ExactNames\Order::sum#1
q
--EXPECTF--
[Successful compilation of %sphpdbg_exact_names.php]
prompt> [Breakpoint #0 added at ExactNames\Order::Total]
prompt> [Breakpoint #1 added at exactnames\order::kind]
prompt> 1
order
[Breakpoint #0 in ExactNames\Order::Total() at %sOrder.sharp:7, hits: 1]
>00007:     public int Total() => 2;
 00008:%w
 00009:     public static string kind() => "order";
prompt> [Breakpoint #2 added at ExactNames\Order::sum#1]
prompt>
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('ExactNames');

$order = new ExactNames\Order();
echo $order->total(), "\n";
echo ExactNames\Order::kind(), "\n";
echo $order->Total(), "\n";
