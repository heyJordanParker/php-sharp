--TEST--
A listener Sharp\Internal\listen starts hears each event after the table's listeners, in the order the listeners started, until its handle goes out of scope, under the tracing JIT
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
require __DIR__ . '/events_scoped.inc';
?>
--EXPECT--
jit on
a handle in a local, until its function returns: table 1, scoped 1, table 2
until an exception leaves its function: table 3, caught boom
a handle never stored: table 4
after the table, in the order they started, and through an interface: table 5, first 5, second 5
one started during an emit hears only the next: table 6, starter 6, table 7, starter 7, late 7
one whose handle is gone runs nothing under a dispatcher that runs it later: table 8
a type no class has: table 9
a handle in a local, until its function returns: table 1, scoped 1, table 2
until an exception leaves its function: table 3, caught boom
a handle never stored: table 4
after the table, in the order they started, and through an interface: table 5, first 5, second 5
one started during an emit hears only the next: table 6, starter 6, table 7, starter 7, late 7
one whose handle is gone runs nothing under a dispatcher that runs it later: table 8
a type no class has: table 9
