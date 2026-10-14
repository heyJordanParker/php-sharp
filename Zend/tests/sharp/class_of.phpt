--TEST--
PHP# Class.of(value) gives the class of the object a value holds, a subclass included, with its type arguments, and typeof gives the class value of any type, a nullable or union type included
--FILE--
<?php

require __DIR__ . '/harness/Factory.inc';
require __DIR__ . '/ClassValues.sharp';

$gold = new Classes\GoldBadge();
var_dump(Classes\ClassValues::of($gold));
var_dump(Classes\ClassValues::of(new Classes\Badge()));
var_dump(Classes\ClassValues::tagOf($gold));
var_dump(Classes\ClassValues::directTag($gold));
var_dump((new Classes\ClassValues())->keptTag());
var_dump(Classes\GoldBadge::own(), Classes\Badge::own(), Classes\GoldBadge::ownTag());
var_dump(Classes\ClassValues::types());
var_dump(Classes\ClassValues::unions());
var_dump(Classes\ClassValues::either(Classes\ClassValues::unions()[1]));
try {
    Classes\ClassValues::either([]);
} catch (TypeError $error) {
    echo $error->getMessage(), "\n";
}
var_dump(Classes\ClassValues::exact('int'));
var_dump(Classes\ClassValues::narrowed());
foreach (['Classes\Badge', 'string|int', 'int'] as $given) {
    try {
        var_dump(Classes\ClassValues::either($given));
    } catch (TypeError $error) {
        echo $error->getMessage(), "\n";
    }
}
try {
    Classes\ClassValues::exact('Classes\Badge');
} catch (TypeError $error) {
    echo $error->getMessage(), "\n";
}
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
--EXPECTF--
string(17) "Classes\GoldBadge"
string(13) "Classes\Badge"
string(4) "gold"
string(4) "gold"
string(4) "gold"
string(17) "Classes\GoldBadge"
string(13) "Classes\Badge"
string(4) "gold"
array(4) {
  [0]=>
  string(3) "int"
  [1]=>
  string(19) "List<Classes.Badge>"
  [2]=>
  string(16) "Map<string, int>"
  [3]=>
  string(8) "Set<int>"
}
array(3) {
  [0]=>
  string(4) "int?"
  [1]=>
  string(10) "int|string"
  [2]=>
  string(20) "(Classes.Badge|int)?"
}
string(10) "int|string"
Classes\ClassValues::either(): Argument #1 ($type) must be of type string, array given, called in %s on line %d
string(3) "int"
string(3) "int"
Classes\ClassValues::either(): Argument #1 ($type) must be of type Class<int|string>, string given, called in %s on line %d
Classes\ClassValues::either(): Argument #1 ($type) must be of type Class<int|string>, string given, called in %s on line %d
Classes\ClassValues::either(): Argument #1 ($type) must be of type Class<int|string>, string given, called in %s on line %d
Classes\ClassValues::exact(): Argument #1 ($type) must be of type Class<int>, string given, called in %s on line %d
bool(true)
bool(false)
bool(true)
string(13) "Classes\Crate"
string(11) "ArrayObject"
bool(true)
Sharp\Class::of(): Argument #1 ($value) must be of type object, string given
