--TEST--
PHP# fields are private or protected typed properties with their initial values
--FILE--
<?php

require __DIR__ . '/Counter.sharp';

foreach ((new ReflectionClass(Demo\Counter::class))->getProperties() as $property) {
    echo $property->isPrivate() ? 'private ' : 'protected ', $property->getType(), ' ', $property->getName(),
        $property->hasDefaultValue() ? ' = ' . var_export($property->getDefaultValue(), true) : '', "\n";
}

$counter = new Demo\Counter();
$counter->add(2);
echo $counter->add(3), "\n";
echo $counter->scaled(), "\n";

try {
    echo $counter->count;
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
private int count = 0
protected float rate = 3.0
private string label
5
15
Cannot access private property Demo\Counter::$count
