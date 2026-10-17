--TEST--
PHP# attributes on an interface method, with or without a default body, read through Reflection
--FILE--
<?php
require __DIR__ . '/harness/Attributes.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('InterfaceAttributes');

foreach (['label', 'title'] as $method) {
    foreach ((new ReflectionMethod(InterfaceAttributes\Labeled::class, $method))->getAttributes() as $attribute) {
        echo $method, ': ', $attribute->getName(), ' ', json_encode($attribute->getArguments()), "\n";
    }
}
echo (new ReflectionMethod(InterfaceAttributes\Labeled::class, 'label'))->getAttributes(Lib\Field::class)[0]->newInstance()->label, "\n";
?>
--EXPECT--
label: Lib\Field {"0":"shown in lists","limit":2}
title: Lib\Entity ["shown in headings"]
title: Lib\Field []
shown in lists
