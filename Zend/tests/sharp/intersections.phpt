--TEST--
An intersection parameter, property, pattern and local take a value of every type the intersection joins, a plain PHP object included, a union holds an intersection with or without parentheses, and plain PHP that passes a value of one of them gets a TypeError
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('Intersections');
require __DIR__ . '/intersections.inc';
?>
--EXPECTF--
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
a union that holds an intersection, as a parameter: ["both","desk","both"]
a plain PHP class that is only Shippable, as the union: TypeError: Intersections\Desk::sort(): Argument #1 ($item) must be of type (Intersections\Shippable&Intersections\Billable)|Intersections\Desk, PlainShippable given, called in %s on line %d
is, of the union that holds an intersection: [true,true,false]
the union type reflection reports: ["(Intersections\\Shippable&Intersections\\Billable)|Intersections\\Desk","(Intersections\\Shippable&Intersections\\Billable)|Intersections\\Desk"]
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
a union that holds an intersection, as a parameter: ["both","desk","both"]
a plain PHP class that is only Shippable, as the union: TypeError: Intersections\Desk::sort(): Argument #1 ($item) must be of type (Intersections\Shippable&Intersections\Billable)|Intersections\Desk, PlainShippable given, called in %s on line %d
is, of the union that holds an intersection: [true,true,false]
the union type reflection reports: ["(Intersections\\Shippable&Intersections\\Billable)|Intersections\\Desk","(Intersections\\Shippable&Intersections\\Billable)|Intersections\\Desk"]
