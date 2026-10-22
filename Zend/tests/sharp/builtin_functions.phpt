--TEST--
PHP# reaches PHP's built-in levenshtein through a project's plain PHP class that wraps it
--FILE--
<?php

require __DIR__ . '/harness/Words.inc';
require __DIR__ . '/Expressions.sharp';

echo Demo\Expressions::describe("cart", new ArrayObject([1, 2, 3]), "carts"), "\n";
?>
--EXPECT--
cart has 3 items, 1 edit from carts
