--TEST--
get_class_vars, property_exists and property access never reach a PHP# object's type arguments
--FILE--
<?php
require __DIR__ . '/harness/Witness.inc';
require __DIR__ . '/harness/Snapshot.inc';
require __DIR__ . '/harness/Sleeper.inc';
require __DIR__ . '/TypeArguments.sharp';

$slot = "\0<sharp>\0types";
$orders = App\Queue::orders();

var_dump(get_class_vars(App\PaginatedList::class));
var_dump(property_exists(App\PaginatedList::class, $slot), property_exists($orders, $slot));
try {
    var_dump($orders->$slot);
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
try {
    $orders->$slot = 'int';
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
var_dump(isset($orders->$slot));
var_dump((new ReflectionObject($orders))->getTypeArguments());
?>
--EXPECT--
array(1) {
  ["items"]=>
  NULL
}
bool(false)
bool(false)
Cannot access property starting with "\0"
Cannot access property starting with "\0"
bool(false)
array(1) {
  [0]=>
  string(9) "App.Order"
}
