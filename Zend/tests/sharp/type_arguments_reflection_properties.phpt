--TEST--
Reflection, get_class_vars, property_exists and property access never reach a PHP# object's type arguments, and refuse to name them
--FILE--
<?php
require __DIR__ . '/type_arguments.inc';

$slot = "\0<sharp>\0types";
$orders = App\Queue::orders();

echo "Reflection\n";
$class = new ReflectionClass(App\PaginatedList::class);
$object = new ReflectionObject($orders);
var_dump(array_map(static fn (ReflectionProperty $property): string => $property->name, $class->getProperties()));
var_dump(array_map(static fn (ReflectionProperty $property): string => $property->name, $object->getProperties()));
var_dump($class->getDefaultProperties());
var_dump($class->hasProperty($slot), $object->hasProperty($slot));
try {
    $class->getProperty($slot);
} catch (ReflectionException $e) {
    echo str_replace("\0", '\0', $e->getMessage()), "\n";
}
try {
    new ReflectionProperty(App\PaginatedList::class, $slot);
} catch (ReflectionException $e) {
    echo str_replace("\0", '\0', $e->getMessage()), "\n";
}
echo preg_match('/Properties \[1\]/', (string) $class), "\n";

echo "get_class_vars, property_exists and property access\n";
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
var_dump(arguments($orders));
?>
--EXPECT--
Reflection
array(1) {
  [0]=>
  string(5) "items"
}
array(1) {
  [0]=>
  string(5) "items"
}
array(0) {
}
bool(false)
bool(false)
Property App\PaginatedList::$\0<sharp>\0types does not exist
Property App\PaginatedList::$\0<sharp>\0types does not exist
1
get_class_vars, property_exists and property access
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
