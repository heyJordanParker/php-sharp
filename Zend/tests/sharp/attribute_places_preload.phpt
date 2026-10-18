--TEST--
Preloaded PHP# attributes on an interface, its methods with or without a default body and its property, an event, a constant and a lambda read through Reflection
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/attribute_places_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(interface_exists(AttributePlaces\Labeled::class, false), class_exists(AttributePlaces\Limits::class, false));
$limits = new AttributePlaces\Limits();
$places = [
    'interface' => new ReflectionClass(AttributePlaces\Labeled::class),
    'method' => new ReflectionMethod(AttributePlaces\Labeled::class, 'label'),
    'method with a default body' => new ReflectionMethod(AttributePlaces\Labeled::class, 'title'),
    'interface property' => new ReflectionProperty(AttributePlaces\Labeled::class, 'size'),
    'event' => new ReflectionClass(AttributePlaces\Paid::class),
    'constant' => new ReflectionClassConstant(AttributePlaces\Limits::class, 'MAX'),
    'lambda' => new ReflectionFunction($limits->twice()),
    'block lambda' => new ReflectionFunction($limits->counted()),
];
foreach ($places as $place => $reflector) {
    foreach ($reflector->getAttributes() as $attribute) {
        echo $place, ': ', $attribute->getName(), ' ', json_encode($attribute->getArguments()), "\n";
    }
}
echo $places['method']->getAttributes(Lib\Field::class)[0]->newInstance()->label, "\n";
echo $limits->twice()(4), ' ', $limits->counted()(), "\n";
?>
--EXPECT--
bool(true)
bool(true)
interface: Lib\Entity ["on the interface"]
method: Lib\Field {"0":"shown in lists","limit":2}
method with a default body: Lib\Entity ["shown in headings"]
method with a default body: Lib\Field []
interface property: Lib\Field ["on the property"]
event: Lib\Entity ["on the event"]
constant: Lib\Field ["on the constant"]
lambda: Lib\Field ["on the lambda"]
block lambda: Lib\Field ["on the block lambda"]
shown in lists
8 1
