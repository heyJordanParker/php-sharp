--TEST--
A PHP# template interpolates any expression in `${…}`, takes JavaScript's escapes and spans lines, and `"…"` never interpolates
--FILE--
<?php

require __DIR__ . '/Expressions.sharp';
require __DIR__ . '/Template.sharp';

echo Demo\Expressions::label("cart", new ArrayObject([1, 2])), "\n";
echo Demo\Template::summary(new ArrayObject([1, 2])), "\n";
?>
--EXPECT--
cart: 2 items! ✓ `${name} {$name} $name`
total
<3>[3] ${x} ABC 😀 ab
