--TEST--
A listener that PHP#'s Events.on or plain PHP's Sharp\Internal\listen starts hears each event after the table's listeners, in the order the listeners started, until its handle goes out of scope
--FILE--
<?php
require __DIR__ . '/events_scoped.inc';
?>
--EXPECT--
a handle in a local, until its function returns: table 1, scoped 1, table 2
until an exception leaves its function: table 3, caught boom
a handle never stored: table 4
after the table, in the order they started, and through an interface: table 5, first 5, second 5
one started during an emit hears only the next: table 6, starter 6, table 7, starter 7, late 7
one whose handle is gone runs nothing under a dispatcher that runs it later: table 8
a type no class has: table 9
Events.on in PHP#, until its method returns: table 10, screen 10, table 11
Events.on through an interface: table 12, any 12, table 13
a handle in a property of the object its listener uses, until the collector collects both: table 14, kept 14, keeper destroyed, table 15
a handle in a local, until its function returns: table 1, scoped 1, table 2
until an exception leaves its function: table 3, caught boom
a handle never stored: table 4
after the table, in the order they started, and through an interface: table 5, first 5, second 5
one started during an emit hears only the next: table 6, starter 6, table 7, starter 7, late 7
one whose handle is gone runs nothing under a dispatcher that runs it later: table 8
a type no class has: table 9
Events.on in PHP#, until its method returns: table 10, screen 10, table 11
Events.on through an interface: table 12, any 12, table 13
a handle in a property of the object its listener uses, until the collector collects both: table 14, kept 14, keeper destroyed, table 15
