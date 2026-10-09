--TEST--
A PHP# template shows an int, a float, a string or a bool in `${…}`, a bool as true or false, takes JavaScript's escapes and spans lines, and `"…"` never interpolates
--FILE--
<?php

require __DIR__ . '/Expressions.sharp';
require __DIR__ . '/Template.sharp';

echo Demo\Expressions::label("cart", new ArrayObject([1, 2])), "\n";
echo Demo\Template::summary(new ArrayObject([1, 2])), "\n";
var_dump(Demo\Template::constant());
var_dump(Demo\Template::constantAfter('name'));
var_dump(Demo\Template::empty());
var_dump(Demo\Template::nested());
echo Demo\Template::paid(true), "\n";
echo Demo\Template::paid(false), "\n";
?>
--EXPECT--
cart: 2 items! ✓ `${name} {$name} $name`
total
<3>[3] ${x} ABC 😀 ab
string(2) "v1"
string(7) "v1 name"
string(0) ""
string(1) "x"
Paid: true
Paid: false
