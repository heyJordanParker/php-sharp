--TEST--
PHP# fields are private or protected typed properties with their initial values, which reflection tells from properties
--FILE--
<?php

require __DIR__ . '/Counter.sharp';

final class Plain
{
    private int $count = 0;
}

foreach ((new ReflectionClass(Demo\Counter::class))->getProperties() as $property) {
    echo $property->isPrivate() ? 'private ' : 'protected ', $property->getType(), ' ', $property->getName(),
        $property->hasDefaultValue() ? ' = ' . var_export($property->getDefaultValue(), true) : '',
        $property->isField() ? ' field' : '', "\n";
}
var_dump((new ReflectionProperty(Plain::class, 'count'))->isField());

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
private int count = 0 field
protected float rate = 3.0 field
private string label field
bool(false)
5
15
Cannot access private property Demo\Counter::$count
