--TEST--
A written Any? type argument matches only Any? when plain PHP enters a PHP# method
--FILE--
<?php
require __DIR__ . '/type_arguments_entry.inc';

use App\Box;
use App\Queue;
use Checks\Pages;

attempt(fn () => Pages::open(new Box()));
attempt(fn () => Pages::open(Queue::box()));
?>
--EXPECTF--
opened
Checks\Pages::open(): Argument #1 ($box) must be of type App.Box<Any?>, App.Box<int> given, called in %s on line %d
