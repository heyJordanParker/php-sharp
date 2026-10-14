--TEST--
An out type parameter takes a subtype and an in one a supertype, at any depth, when plain PHP enters a PHP# method, and a plain PHP object's bound takes neither
--FILE--
<?php
require __DIR__ . '/type_arguments_entry.inc';

use Checks\Pages;
use Checks\Stream;

attempt(fn () => Pages::read(Pages::orders()));
attempt(fn () => Pages::nested(Pages::streams()));
attempt(fn () => Pages::read(new Stream()));
attempt(fn () => Pages::drain(Pages::entities()));
attempt(fn () => Pages::drain(Pages::customerDrain()));
var_dump(Pages::fed(Pages::orders()), Pages::fed(Pages::customerStream()), Pages::fed(Pages::streams()));
?>
--EXPECTF--
read
nested
Checks\Pages::read(): Argument #1 ($feed) must be of type Checks.Feed<App.DatabaseEntity>, Checks.Stream<Any?> given, called in %s on line %d
drained
Checks\Pages::drain(): Argument #1 ($sink) must be of type Checks.Sink<App.Order>, Checks.Drain<Checks.Customer> given, called in %s on line %d
bool(true)
bool(true)
bool(false)
