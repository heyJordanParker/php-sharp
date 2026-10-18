--TEST--
A preloaded generic event reaches the listeners on its type arguments and on the interface it implements, as a request with no preloading does
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.preload={PWD}/generic_events_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(GenericEvents\Saved::class, false));
require __DIR__ . '/harness/fixture.inc';
fixture('GenericEvents');
require __DIR__ . '/generic_events.inc';
?>
--EXPECT--
bool(true)
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
