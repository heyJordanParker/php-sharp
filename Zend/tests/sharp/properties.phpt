--TEST--
PHP# auto-properties are public properties with their set visibility and initial values
--FILE--
<?php

require __DIR__ . '/Profile.sharp';

foreach ((new ReflectionClass(Demo\Profile::class))->getProperties() as $property) {
    $set = match (true) {
        $property->isReadOnly() => ' readonly',
        $property->isPrivateSet() => ' private(set)',
        $property->isProtectedSet() => ' protected(set)',
        default => '',
    };
    echo 'public', $set, ' ', $property->getType(), ' ', $property->getName(),
        $property->hasDefaultValue() ? ' = ' . var_export($property->getDefaultValue(), true) : '', "\n";
}

$profile = new Demo\Profile(7);
$profile->visit();
$profile->name = 'ada';
echo $profile->visit(), ' ', $profile->name, ' ', $profile->id, ' ', $profile->score, "\n";

foreach (['views', 'id', 'score'] as $name) {
    try {
        $profile->$name = 1;
    } catch (Error $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
public private(set) int views = 0
public string name = 'guest'
public readonly int id
public protected(set) float score
2 ada 7 0.5
Cannot modify private(set) property Demo\Profile::$views from global scope
Cannot modify readonly property Demo\Profile::$id
Cannot modify protected(set) property Demo\Profile::$score from global scope
