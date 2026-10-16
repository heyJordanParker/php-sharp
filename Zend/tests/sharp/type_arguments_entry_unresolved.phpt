--TEST--
A type parameter plain PHP leaves unresolved matches any type at any depth inside a type, and a written Any? still matches only Any?, while a parameter whose type is the type parameter, alone or nullable, takes only what fits its bound, read with the object's type arguments
--FILE--
<?php
require __DIR__ . '/type_arguments_entry.inc';

use Checks\Page;
use Checks\Pages;
use Checks\Rack;

attempt(fn () => Pages::count(Pages::boxedOrders()));
attempt(fn () => Pages::count(new Page()));
attempt(fn () => Pages::boxed(Rack::box()));
attempt(fn () => Pages::boxed(Rack::customerBox()));
attempt(fn () => Pages::boxed(new App\Box()));
attempt(fn () => Pages::maybeBoxed(null));
attempt(fn () => Pages::maybeBoxed(new App\Box()));
attempt(fn () => Rack::orders()->add(new App\Order(1)));
attempt(fn () => Rack::orders()->add(new Checks\Customer(2)));
attempt(fn () => (new Rack())->add(new Checks\Customer(2)));
// A static method's bound names the class's type parameter, which a call from plain PHP gives the class's bound.
attempt(fn () => Rack::put(new Checks\Customer(2)));
attempt(fn () => Rack::boxed(new App\Box()));
attempt(fn () => Rack::boxed(Rack::customerBox()));
?>
--EXPECTF--
1
Checks\Pages::count(): Argument #1 ($page) must be of type Checks.Page<App.Box<?>>, Checks.Page<Any?> given, called in %s on line %d
boxed
Checks\Pages::boxed(): Argument #1 ($box) must be of type App.Box<App.Order>, App.Box<Checks.Customer> given, called in %s on line %d
Checks\Pages::boxed(): Argument #1 ($box) must be of type App.Box<App.Order>, App.Box<Any?> given, called in %s on line %d
maybe boxed
Checks\Pages::maybeBoxed(): Argument #1 ($box) must be of type App.Box<App.Order>?, App.Box<Any?> given, called in %s on line %d
added
Checks\Rack::add(): Argument #1 ($item) must be of type App.Order, Checks.Customer given, called in %s on line %d
added
put
boxed
boxed
