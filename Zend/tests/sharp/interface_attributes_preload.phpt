--TEST--
A preloaded PHP# interface method keeps its attributes, with or without a default body, for Reflection
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/interface_attributes_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(interface_exists(InterfaceAttributes\Labeled::class, false));
foreach (['label', 'title'] as $method) {
    foreach ((new ReflectionMethod(InterfaceAttributes\Labeled::class, $method))->getAttributes() as $attribute) {
        echo $method, ': ', $attribute->getName(), ' ', json_encode($attribute->getArguments()), "\n";
    }
}
echo (new ReflectionMethod(InterfaceAttributes\Labeled::class, 'label'))->getAttributes(Lib\Field::class)[0]->newInstance()->label, "\n";
?>
--EXPECT--
bool(true)
label: Lib\Field {"0":"shown in lists","limit":2}
title: Lib\Entity ["shown in headings"]
title: Lib\Field []
shown in lists
