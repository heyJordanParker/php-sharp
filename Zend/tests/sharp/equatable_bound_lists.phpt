--TEST--
Two Lists of a type parameter bounded by Equatable compare element by element, each element by its type's ==, so two Lists of equal structs are equal
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Equatables\Lists;

var_dump(Lists::points(1, 1));
var_dump(Lists::points(1, 2));
?>
--EXPECT--
bool(true)
bool(false)
