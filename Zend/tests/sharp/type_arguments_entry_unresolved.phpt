--TEST--
A type parameter plain PHP leaves unresolved matches any type at any depth, and a written Any? still matches only Any?
--FILE--
<?php
require __DIR__ . '/type_arguments_entry.inc';

use Checks\Page;
use Checks\Pages;

attempt(fn () => Pages::count(Pages::boxedOrders()));
attempt(fn () => Pages::count(new Page()));
?>
--EXPECTF--
1
Checks\Pages::count(): Argument #1 ($page) must be of type Checks.Page<App.Box<?>>, Checks.Page<Any?> given, called in %s on line %d
