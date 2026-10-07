--TEST--
PHP# chooses a value with the ternary, nested in parentheses
--FILE--
<?php

require __DIR__ . '/Checkout.sharp';

var_dump(Demo\Checkout::host("shop.test"), Demo\Checkout::host(""));
var_dump(Demo\Checkout::sign(3), Demo\Checkout::sign(-3), Demo\Checkout::sign(0));
var_dump(Demo\Checkout::size(50, true), Demo\Checkout::size(50, false), Demo\Checkout::size(500, true));
?>
--EXPECT--
string(9) "shop.test"
NULL
string(8) "positive"
string(8) "negative"
string(4) "zero"
string(5) "small"
string(5) "large"
string(5) "large"
