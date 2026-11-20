--TEST--
A method's result and parameter types keep their type arguments, spec section 25: `Method.type` and `Parameter.type` of a PHP# generic class and of a plain PHP one give their definition and each type argument, as `typeof(Box<Order>)` does
--FILE--
<?php
require __DIR__ . '/harness/Attributes.inc';
require __DIR__ . '/harness/Crate.inc';
require __DIR__ . '/vendor/autoload.php';

foreach (App\Shop\Shelf::describe() as $line) {
    echo $line, "\n";
}
var_dump(App\Shop\Shelf::compare());
?>
--EXPECT--
boxed: App.Shop.Box[App.Shop.Order] ()
keep: null[] (App.Shop.Box[App.Shop.Order])
names: Illuminate.Support.Collection[int, string] ()
array(7) {
  [0]=>
  bool(true)
  [1]=>
  bool(true)
  [2]=>
  bool(true)
  [3]=>
  bool(true)
  [4]=>
  bool(true)
  [5]=>
  bool(true)
  [6]=>
  bool(true)
}
