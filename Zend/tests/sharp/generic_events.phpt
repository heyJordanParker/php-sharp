--TEST--
emit new Saved<Order> runs the listeners on Saved<Order> and on the interface Saved implements, never those on Saved<Invoice>, and so do Events.on, plain PHP's emit and a PSR-14 dispatcher
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('GenericEvents');
require __DIR__ . '/generic_events.inc';
?>
--EXPECT--
emit new Saved<Order>: order 7, entity
emit new Saved<Invoice>: invoice 8, entity
Events.on<Saved<Order>> and a Saved<Order>: order 9, entity, scoped 9
Events.on<Saved<Order>> and a Saved<Invoice>: invoice 10, entity
plain PHP emits a Saved<Invoice>: invoice 11, entity
a PSR-14 dispatcher runs the listeners of a Saved<Invoice>: invoice 12, entity
emit new Saved<Order>: order 7, entity
emit new Saved<Invoice>: invoice 8, entity
Events.on<Saved<Order>> and a Saved<Order>: order 9, entity, scoped 9
Events.on<Saved<Order>> and a Saved<Invoice>: invoice 10, entity
plain PHP emits a Saved<Invoice>: invoice 11, entity
a PSR-14 dispatcher runs the listeners of a Saved<Invoice>: invoice 12, entity
