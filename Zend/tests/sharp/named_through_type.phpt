--TEST--
new TItem.fromJson(…) calls the required named constructor of the type argument the code runs with, a subclass's own one for a subclass, and plain PHP calls the static method it runs as
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('NamedThroughType');
require __DIR__ . '/named_through_type.inc';
?>
--EXPECT--
new TItem.fromJson for Repository<Invoice> and Repository<Record>: invoice invoice 7, record record 8
plain PHP calls the named constructor it runs as: NamedThroughType\Invoice invoice 9
new TItem.fromJson for Repository<Invoice> and Repository<Record>: invoice invoice 7, record record 8
plain PHP calls the named constructor it runs as: NamedThroughType\Invoice invoice 9
