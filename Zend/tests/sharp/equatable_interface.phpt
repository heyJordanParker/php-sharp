--TEST--
Two values of an interface that extends Equatable are equal when they are the same type and that type's == holds: one enum's cases by case, two enums never, a struct by value, so a List compares them element by element, a Set holds one of equal values and a Map keys them
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Equatables\Failures;

var_dump(Failures::cardIsCard());
var_dump(Failures::cardIsFraud());
var_dump(Failures::cardIsGone());
var_dump(Failures::cardIsNotGone());
var_dump(Failures::codes(4, 4));
var_dump(Failures::codes(4, 5));
var_dump(Failures::lists(4));
var_dump(Failures::held(4));
var_dump(Failures::keyed(4));
?>
--EXPECT--
bool(true)
bool(false)
bool(false)
bool(true)
bool(true)
bool(false)
bool(true)
int(3)
int(2)
