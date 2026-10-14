--TEST--
PHP# Class.of(value) gives the class of the object a value holds, a subclass included
--FILE--
<?php

require __DIR__ . '/harness/Factory.inc';
require __DIR__ . '/ClassValues.sharp';

$gold = new Classes\GoldBadge();
var_dump(Classes\ClassValues::of($gold));
var_dump(Classes\ClassValues::of(new Classes\Badge()));
var_dump(Classes\ClassValues::tagOf($gold));
var_dump(Sharp\Class::of(new ArrayObject()));
var_dump((new ReflectionClass(Sharp\Class::class))->isFinal());
try {
    Sharp\Class::of('Classes\Badge');
} catch (TypeError $error) {
    echo $error->getMessage(), "\n";
}
?>
--EXPECT--
string(17) "Classes\GoldBadge"
string(13) "Classes\Badge"
string(4) "gold"
string(11) "ArrayObject"
bool(true)
Sharp\Class::of(): Argument #1 ($value) must be of type object, string given
