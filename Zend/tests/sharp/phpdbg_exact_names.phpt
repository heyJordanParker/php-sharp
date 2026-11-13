--TEST--
phpdbg finds a PHP# class by its exact name, and its methods too, spec section 24: `break ExactNames\Order::total` and `break ExactNames\Order::Total` are two breakpoints, each stopping in its own method and not in its twin, a breakpoint on `exactnames\order::kind` names no PHP# method, and `break ExactNames\Order::sum#1` resolves in the loaded class at once
--PHPDBG--
b ExactNames\Order::Total
b ExactNames\Order::total
b exactnames\order::kind
r
c
b ExactNames\Order::sum#1
q
--EXPECTF--
[Successful compilation of %sphpdbg_exact_names.php]
prompt> [Breakpoint #0 added at ExactNames\Order::Total]
prompt> [Breakpoint #1 added at ExactNames\Order::total]
prompt> [Breakpoint #2 added at exactnames\order::kind]
prompt> [Breakpoint #1 in ExactNames\Order::total() at %sOrder.sharp:5, hits: 1]
>00005:     public int total() => 1;
 00006:%w
 00007:     public int Total() => 2;
prompt> 1
order
[Breakpoint #0 in ExactNames\Order::Total() at %sOrder.sharp:7, hits: 1]
>00007:     public int Total() => 2;
 00008:%w
 00009:     public static string kind() => "order";
prompt> [Breakpoint #3 added at ExactNames\Order::sum#1]
prompt>
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('ExactNames');

$order = new ExactNames\Order();
echo $order->total(), "\n";
echo ExactNames\Order::kind(), "\n";
echo $order->Total(), "\n";
