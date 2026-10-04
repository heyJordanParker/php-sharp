--TEST--
A PHP# get-only property is readonly: set once in the constructor, and never again
--FILE--
<?php

require __DIR__ . '/Badge.sharp';

$label = new ReflectionProperty(Demo\Badge::class, 'label');
echo $label->isReadOnly() ? 'readonly' : 'writable', ' ', $label->hasDefaultValue() ? 'with a default' : 'set in the constructor', "\n";

$badge = new Demo\Badge(4);
echo $badge->id, ' ', $badge->label, "\n";

try {
    $badge->rename('old');
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
readonly set in the constructor
4 new
Cannot modify readonly property Demo\Badge::$label
