--TEST--
A Set type argument round-trips through getTypeArguments and serialize, and plain PHP entering a method that takes a Box<Set<Order>> and a Set<Order> gets a TypeError only for a box of other type arguments
--FILE--
<?php
require __DIR__ . '/TypeArgumentsSets.sharp';

use Shelf\Boxes;
use Shelf\Order;

function attempt(callable $call): void
{
    try {
        echo $call(), "\n";
    } catch (TypeError $error) {
        echo $error->getMessage(), "\n";
    }
}

$ints = Boxes::ints();
var_dump((new ReflectionObject($ints))->getTypeArguments());
var_dump((new ReflectionObject(unserialize(serialize($ints))))->getTypeArguments());
attempt(fn () => Boxes::count(Boxes::orders(), [Order::Open, Order::Shipped, Order::Open]));
attempt(fn () => Boxes::count($ints, [Order::Open]));
?>
--EXPECTF--
array(1) {
  [0]=>
  string(8) "Set<int>"
}
array(1) {
  [0]=>
  string(8) "Set<int>"
}
2
Shelf\Boxes::count(): Argument #1 ($box) must be of type Shelf.Box<Set<Shelf.Order>>, Shelf.Box<Set<int>> given, called in %s on line %d
