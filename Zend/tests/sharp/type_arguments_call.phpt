--TEST--
A generic call gives the method the type arguments it writes or the checker infers, static or not, through a method value, one a generic method makes from its own, a spread, a plain PHP @template interface, parent or super call and across a Fiber suspension, and a method passes its own and its class's on to the next generic call and reads them in is, as, a match arm, typeof, new, a static member and a catch clause, and each type argument of a generic class keeps its own static fields, which every write changes in place, a subclass shares through its header, and a first use copies from the defaults, and a class value carries its type arguments through plain PHP to new and to a static member it reads or writes, and enters from plain PHP, as an argument or a property value, only as a class value of its type
--FILE--
<?php
require __DIR__ . '/type_arguments_call.inc';
?>
--EXPECTF--
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
1, 1
App\Box<App.Order>
App\Box<App.Order>
App\Pair<int, App.Order>
App\Pair<int, int>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
suspended
App\Box<App.Order>
bool(true)
bool(false)
NULL
int(4)
item, other, box
App\Order, 20, App\Order 5
bool(true)
bool(false)
App\Order, 20
numbers: one
error: two
error: four
rethrown: three
Cannot read the type arguments of the class a lambda is written in from an object of another class
Cannot read the type arguments of the class a lambda is written in from an object of another class
Cannot read the type arguments of the class a lambda is written in from an object of another class
8, 9, 2, 9, 3, 9, 40, 0
101, 2, 101
5, 7, 0
string(17) "App\PaginatedList"
bool(true)
bool(true)
App\PaginatedList<App.Order>, App\PaginatedList<App.SharedOrder>, App\PaginatedList<App.Order>
App\PaginatedList<App.Order>, App\Order 8, 82
8, 8, 1, 0
App\OrderPage<>
Calls\Repository::paged(): Argument #1 ($type) must be of type Class<App.PaginatedList<App.Order>>, Class<App.PaginatedList<App.SharedOrder>> given, called in %s on line %d
Calls\Repository::paged(): Argument #1 ($type) must be of type Class<App.PaginatedList<App.Order>>, string given, called in %s on line %d
Calls\Repository::paged(): Argument #1 ($type) must be of type Class<App.PaginatedList<App.Order>>, string given, called in %s on line %d
Calls\Repository::paged(): Argument #1 ($type) must be of type Class<App.PaginatedList<App.Order>>, string given, called in %s on line %d
written: orderPages
Cannot assign Class<App.PaginatedList<App.SharedOrder>> to property Calls\Shelf::$pages of type Class<App.PaginatedList<App.Order>>?
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
1, 1
App\Box<App.Order>
App\Box<App.Order>
App\Pair<int, App.Order>
App\Pair<int, int>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
suspended
App\Box<App.Order>
bool(true)
bool(false)
NULL
int(4)
item, other, box
App\Order, 20, App\Order 5
bool(true)
bool(false)
App\Order, 20
numbers: one
error: two
error: four
rethrown: three
Cannot read the type arguments of the class a lambda is written in from an object of another class
Cannot read the type arguments of the class a lambda is written in from an object of another class
Cannot read the type arguments of the class a lambda is written in from an object of another class
8, 9, 2, 9, 3, 9, 40, 0
101, 2, 101
3, 7, 0
string(17) "App\PaginatedList"
bool(true)
bool(true)
App\PaginatedList<App.Order>, App\PaginatedList<App.SharedOrder>, App\PaginatedList<App.Order>
App\PaginatedList<App.Order>, App\Order 8, 82
8, 8, 2, 0
App\OrderPage<>
Calls\Repository::paged(): Argument #1 ($type) must be of type Class<App.PaginatedList<App.Order>>, Class<App.PaginatedList<App.SharedOrder>> given, called in %s on line %d
Calls\Repository::paged(): Argument #1 ($type) must be of type Class<App.PaginatedList<App.Order>>, string given, called in %s on line %d
Calls\Repository::paged(): Argument #1 ($type) must be of type Class<App.PaginatedList<App.Order>>, string given, called in %s on line %d
Calls\Repository::paged(): Argument #1 ($type) must be of type Class<App.PaginatedList<App.Order>>, string given, called in %s on line %d
written: orderPages
Cannot assign Class<App.PaginatedList<App.SharedOrder>> to property Calls\Shelf::$pages of type Class<App.PaginatedList<App.Order>>?
