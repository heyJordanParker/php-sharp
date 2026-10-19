--TEST--
new TItem.fromJson(…) calls the required named constructor of the type argument the code runs with, a subclass's own one for a subclass, through a bound below the class that requires it, and new Self.fromJson(…) the one of the class the code is called on, and plain PHP calls the static method it runs as
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('NamedThroughType');
require __DIR__ . '/named_through_type.inc';
?>
--EXPECT--
new TItem.fromJson for Repository<Invoice> and Repository<Record>: invoice invoice 7, record record 8
plain PHP calls the named constructor it runs as: NamedThroughType\Invoice invoice 9
a bound below the class that requires fromJson: receipt receipt 3, receipt receipt 4
new Self.fromJson on the class the code is called on: NamedThroughType\Invoice invoice 5, NamedThroughType\Invoice invoice copy, record 6
new TItem.fromJson for Repository<Invoice> and Repository<Record>: invoice invoice 7, record record 8
plain PHP calls the named constructor it runs as: NamedThroughType\Invoice invoice 9
a bound below the class that requires fromJson: receipt receipt 3, receipt receipt 4
new Self.fromJson on the class the code is called on: NamedThroughType\Invoice invoice 5, NamedThroughType\Invoice invoice copy, record 6
