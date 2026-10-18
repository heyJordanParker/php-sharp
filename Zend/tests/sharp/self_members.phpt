--TEST--
Self.name reads and writes a constant or a static member of the class the code is called on, a subclass's own one when the call ran on the subclass, and the statics of each type argument of a generic class, and plain PHP reads the same values
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
Self.TABLE called on the class and on its subclass: items orders
Self.TABLE through an object: items orders
Self.count written on each class: 4 9
plain PHP reads the statics: 4 9
Self.seen.add, which both classes share: 3 4 items,orders,items,orders
Self.count of each type argument: 4 2
