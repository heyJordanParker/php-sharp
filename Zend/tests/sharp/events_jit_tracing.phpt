--TEST--
emit runs every listener of an event before it returns, in table order, each once, and an exception from one stops the rest, under the tracing JIT
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.jit=tracing
opcache.jit_buffer_size=32M
opcache.jit_hot_func=1
opcache.jit_hot_loop=1
opcache.jit_hot_return=1
opcache.jit_hot_side_exit=1
--FILE--
<?php
echo 'jit ', opcache_get_status()['jit']['on'] ? 'on' : 'off', "\n";
require __DIR__ . '/events.inc';
?>
--EXPECT--
jit on
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
