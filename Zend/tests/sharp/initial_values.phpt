--TEST--
A PHP# initial value that is not constant runs at the start of the constructor, in declaration order
--FILE--
<?php

require __DIR__ . '/Inventory.sharp';

echo (new Demo\Inventory(5))->total(), "\n";
echo (new Demo\Shelf())->stock->count, "\n";

foreach ([Demo\Inventory::class, Demo\Shelf::class] as $class) {
    $constructor = (new ReflectionClass($class))->getConstructor();
    echo $class, ' ', $constructor->getName(), ' ', $constructor->getStartLine(), '-', $constructor->getEndLine(), "\n";
}
?>
--EXPECT--
21
7
Demo\Inventory __construct 24-27
Demo\Shelf __construct 35-38
