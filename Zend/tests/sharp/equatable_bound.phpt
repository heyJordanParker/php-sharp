--TEST--
A value of a type parameter bounded by Equatable runs its type's ==: a struct compares by value, a class by its operator ==
--FILE--
<?php
require_once __DIR__ . '/harness/fixture.inc';
fixture('EquatableBound');

use Equatables\Generic;
use Equatables\Order;
use Equatables\Point;

var_dump(Generic::points(1, 1));
var_dump(Generic::points(1, 2));
var_dump(Generic::orders(3, 3));
var_dump(Generic::orders(3, 4));
var_dump(Generic::entities(5, 5));
var_dump(Generic::numbers(6, 6));
var_dump(Generic::lists([1, 2], [1, 2]));
var_dump(Generic::pointListed(2));
var_dump(Generic::keys([new Point(1), new Point(1), new Point(2)]));
var_dump(Generic::same(new Order(7), new Order(7)));
?>
--EXPECT--
bool(true)
bool(false)
bool(true)
bool(false)
bool(true)
bool(true)
bool(true)
bool(true)
int(2)
bool(true)
