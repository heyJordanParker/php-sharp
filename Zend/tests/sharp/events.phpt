--TEST--
emit runs every listener of an event before it returns, in table order, each once, and an exception from one stops the rest
--FILE--
<?php
require __DIR__ . '/events.inc';
?>
--EXPECT--
send 7, refresh, audit 7, deliver 7, ship 7
send 8, audit 8, reissue 8, send 108, refresh, audit 108, deliver 108, ship 108, restock 8
order 9 has a negative amount, after send 9, refresh, audit 9
send 10, refresh, audit 10, deliver 10, ship 10
alert mail
nothing
send 7, refresh, audit 7, deliver 7, ship 7
send 8, audit 8, reissue 8, send 108, refresh, audit 108, deliver 108, ship 108, restock 8
order 9 has a negative amount, after send 9, refresh, audit 9
send 10, refresh, audit 10, deliver 10, ship 10
alert mail
nothing
Sharp\Internal\emit(): Argument #1 ($event) must be of type object, int given
Cannot modify readonly property Shop\OrderPaid::$amount
