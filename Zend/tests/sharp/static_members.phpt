--TEST--
PHP# class constants and static members are PHP class constants and static properties
--FILE--
<?php

require __DIR__ . '/Registry.inc';
require __DIR__ . '/Members.sharp';

$class = new ReflectionClass(Demo\Members::class);

var_dump(Demo\Members::START);
var_dump($class->getReflectionConstant('LABEL')->isProtected());
var_dump($class->getConstant('LABEL'));
var_dump(Demo\Members::$last);
var_dump($class->getStaticPropertyValue('count'));

Demo\Members::touch();

var_dump(Demo\Members::$last);
var_dump($class->getStaticPropertyValue('count'));

try {
    Demo\Members::$last = "outside";
} catch (Error $e) {
    echo get_class($e), "\n";
}
?>
--EXPECT--
int(10)
bool(true)
string(7) "members"
string(4) "none"
int(0)
string(7) "touched"
int(7)
Error
