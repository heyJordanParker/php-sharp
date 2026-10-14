--TEST--
PHP# Class.of(value) gives the class of the object a value holds, a subclass included, with its type arguments
--FILE--
<?php

require __DIR__ . '/harness/Factory.inc';
require __DIR__ . '/ClassValues.sharp';

$gold = new Classes\GoldBadge();
var_dump(Classes\ClassValues::of($gold));
var_dump(Classes\ClassValues::of(new Classes\Badge()));
var_dump(Classes\ClassValues::tagOf($gold));
$crate = Classes\ClassValues::crate();
var_dump(Classes\ClassValues::sameAsTypeof($crate));
var_dump(Classes\ClassValues::otherThanTypeof($crate));
var_dump(Classes\ClassValues::remadeKeepsTypeArguments($crate));
var_dump(Sharp\Class::of($crate));
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
bool(true)
bool(false)
bool(true)
string(13) "Classes\Crate"
string(11) "ArrayObject"
bool(true)
Sharp\Class::of(): Argument #1 ($value) must be of type object, string given
