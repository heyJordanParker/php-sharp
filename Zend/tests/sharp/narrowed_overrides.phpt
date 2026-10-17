--TEST--
PHP# an override of a typed plain PHP parent property may narrow its type: the class links with the parent's PHP type, and a write the narrower type refuses, from the parent's code or plain PHP, is a TypeError
--FILE--
<?php
require __DIR__ . '/harness/Gauge.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('NarrowedOverrides');

$meter = new NarrowedOverrides\Meter();
echo $meter->read(), ' | ', $meter->describe(), "\n";
$meter->relabel('dial');
$meter->level = 7;
echo $meter->read(), "\n";

foreach ([fn () => $meter->relabel(null), fn () => $meter->level = 1.5] as $write) {
    try {
        $write();
    } catch (TypeError $error) {
        echo $error->getMessage(), "\n";
    }
}
echo $meter->read(), "\n";

foreach (['label', 'level'] as $name) {
    echo $name, ': ', (new ReflectionProperty(NarrowedOverrides\Meter::class, $name))->getType(), "\n";
}
?>
--EXPECT--
meter 3 | meter 3
dial 7
Cannot assign null to property NarrowedOverrides\Meter::$label of type string
Cannot assign float to property NarrowedOverrides\Meter::$level of type int
dial 7
label: ?string
level: int|float
