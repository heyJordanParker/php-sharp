--TEST--
Reflection, get_class_vars, property_exists and property access never reach a PHP# object's type arguments or a generic class's static fields, and refuse to name them
--FILE--
<?php
require __DIR__ . '/type_arguments_calls.inc';

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

echo "A generic class's static fields\n";
$statics = "\0<sharp>\0statics";
$counter = new ReflectionClass(Calls\Counter::class);
Calls\Counter::track(new App\Order(1));
var_dump($counter->getStaticProperties(), get_class_vars(Calls\Counter::class));
var_dump(array_map(static fn (ReflectionProperty $property): string => $property->name, $counter->getProperties()));
var_dump($counter->hasProperty($statics), property_exists(Calls\Counter::class, $statics));
try {
    $counter->getStaticPropertyValue($statics);
} catch (ReflectionException $e) {
    echo str_replace("\0", '\0', $e->getMessage()), "\n";
}
foreach (['count', $statics] as $name) {
    try {
        var_dump(Calls\Counter::$$name);
    } catch (Error $e) {
        echo str_replace("\0", '\0', $e->getMessage()), "\n";
    }
}
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
A generic class's static fields
array(0) {
}
array(0) {
}
array(0) {
}
bool(false)
bool(false)
Property Calls\Counter::$\0<sharp>\0statics does not exist
Access to undeclared static property Calls\Counter::$count
Access to undeclared static property Calls\Counter::$
