--TEST--
A preloaded class's intersection parameter, property, pattern and local take a value of every type the intersection joins, and refuse a value of one of them, as a request with no preloading does
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.preload={PWD}/intersections_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(Intersections\Desk::class, false));
require __DIR__ . '/harness/fixture.inc';
fixture('Intersections');
require __DIR__ . '/intersections.inc';
?>
--EXPECTF--
bool(true)
a PHP# class that is both, as a parameter: 60
a plain PHP class that is both, as a parameter: 20
a plain PHP class that is only Shippable, as a parameter: TypeError: Intersections\Desk::cost(): Argument #1 ($item) must be of type Intersections\Shippable&Intersections\Billable, PlainShippable given, called in %s on line %d
a plain PHP class that is both, as a property: "PlainBoth"
a plain PHP class that is only Shippable, as a property: TypeError: Cannot assign PlainShippable to property Intersections\Desk::$last of type (Intersections\Shippable&Intersections\Billable)|null
null, as the nullable property: null
as, of a value that is both and of one that is not: ["PlainBoth","Intersections\\Parcel"]
is, of a value that is both and of one that is not: ["both 9","not both"]
a local of the intersection: 30
the type reflection reports: "Intersections\\Shippable&Intersections\\Billable"
a PHP# class that is both, as a parameter: 60
a plain PHP class that is both, as a parameter: 20
a plain PHP class that is only Shippable, as a parameter: TypeError: Intersections\Desk::cost(): Argument #1 ($item) must be of type Intersections\Shippable&Intersections\Billable, PlainShippable given, called in %s on line %d
a plain PHP class that is both, as a property: "PlainBoth"
a plain PHP class that is only Shippable, as a property: TypeError: Cannot assign PlainShippable to property Intersections\Desk::$last of type (Intersections\Shippable&Intersections\Billable)|null
null, as the nullable property: null
as, of a value that is both and of one that is not: ["PlainBoth","Intersections\\Parcel"]
is, of a value that is both and of one that is not: ["both 9","not both"]
a local of the intersection: 30
the type reflection reports: "Intersections\\Shippable&Intersections\\Billable"
