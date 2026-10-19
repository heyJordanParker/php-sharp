--TEST--
A preloaded class's Self.name reads and writes the members of the class the code is called on, and of each type argument of a generic class, as a request with no preloading does
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.preload={PWD}/self_members_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(SelfMembers\Counter::class, false));
require __DIR__ . '/harness/fixture.inc';
fixture('SelfMembers');
require __DIR__ . '/self_members.inc';
?>
--EXPECT--
bool(true)
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
