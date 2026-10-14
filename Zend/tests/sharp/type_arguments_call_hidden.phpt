--TEST--
A generic method keeps its type arguments in a local that get_defined_vars, a lambda's var_dump and reflection of the lambda never show
--FILE--
<?php
require __DIR__ . '/type_arguments_calls.inc';

use App\Order;
use Calls\Repository;

$order = new Order(1);
var_dump(array_keys(Repository::locals($order)));
$held = Repository::orderHeld($order);
var_dump($held);
$reflection = new ReflectionFunction($held);
var_dump(array_keys($reflection->getStaticVariables()));
var_dump(array_keys($reflection->getClosureUsedVariables()));
echo $reflection;
?>
--EXPECTF--
array(1) {
  [0]=>
  string(4) "item"
}
object(Closure)#%d (%d) {
  ["name"]=>
  string(%d) "{closure:Calls\Repository::held():%d}"
  ["file"]=>
  string(%d) "%sTypeArgumentsCalls.sharp"
  ["line"]=>
  int(%d)
  ["static"]=>
  array(1) {
    ["item"]=>
    object(App\Order)#%d (1) {
      ["id"]=>
      int(1)
    }
  }
}
array(1) {
  [0]=>
  string(4) "item"
}
array(1) {
  [0]=>
  string(4) "item"
}
Closure [ <user> public method {closure:Calls\Repository::held():%d} ] {
  @@ %sTypeArgumentsCalls.sharp %d - %d

  - Bound Variables [1] {
      Variable #0 [ $item ]
  }
}
