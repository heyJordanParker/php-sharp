--TEST--
Self.name reads and writes a constant or a static member of the class the code is called on, a subclass's own one when the call ran on the subclass, in a body, a parameter default and a pattern, and the statics of each type argument of a generic class, which a static method's value keeps, and plain PHP reads the same values
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('SelfMembers');
require __DIR__ . '/self_members.inc';
?>
--EXPECT--
Self.TABLE called on the class and on its subclass: items orders
Self.TABLE through an object: items orders
Self.count written on each class: 2 7
plain PHP reads the statics: 2 7
Self.seen.add, which both classes share: 1 2 items,orders
Self.count of each type argument: 2 1
the values Counter<Store>.bump and Self.bump keep their type arguments: 3 3
a default of Self.TABLE and Self.name(): items orders store order store
a pattern of Self.TABLE: true true false own table, store table
Self.TABLE called on the class and on its subclass: items orders
Self.TABLE through an object: items orders
Self.count written on each class: 4 9
plain PHP reads the statics: 4 9
Self.seen.add, which both classes share: 3 4 items,orders,items,orders
Self.count of each type argument: 5 4
the values Counter<Store>.bump and Self.bump keep their type arguments: 6 6
a default of Self.TABLE and Self.name(): items orders store order store
a pattern of Self.TABLE: true true false own table, store table
