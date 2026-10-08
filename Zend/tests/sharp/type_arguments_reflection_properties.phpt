--TEST--
Reflection lists no property for a PHP# object's type arguments, and refuses to name it
--FILE--
<?php
require __DIR__ . '/harness/Witness.inc';
require __DIR__ . '/harness/Snapshot.inc';
require __DIR__ . '/harness/Sleeper.inc';
require __DIR__ . '/TypeArguments.sharp';

$slot = "\0<sharp>\0types";
$class = new ReflectionClass(App\PaginatedList::class);
$object = new ReflectionObject(App\Queue::orders());

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
?>
--EXPECT--
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
