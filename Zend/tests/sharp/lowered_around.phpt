--TEST--
PHP# compiles an override of a property whose type erases, a named constructor whose `__` name is no magic method's, and a case named `class` that carries data, and each runs
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

echo LoweredAround\Uses::holder(), "\n";
echo LoweredAround\Uses::maker(), "\n";
echo LoweredAround\Uses::shape(), "\n";

// The override runs with the type its parent erases to, and checks a write from plain PHP against its own type.
echo (new ReflectionProperty(LoweredAround\HolderChild::class, 'value'))->getType(), "\n";
$child = new LoweredAround\HolderChild('first');
try {
    $child->value = 5;
} catch (TypeError $error) {
    echo $error->getMessage(), "\n";
}
?>
--EXPECT--
second
4
class with 3 sides
mixed
Cannot assign int to property LoweredAround\HolderChild::$value of type string
