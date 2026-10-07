--TEST--
PHP# parses request strings with Int and Float, which throw at the .sharp line or give null
--FILE--
<?php

require __DIR__ . '/Checkout.sharp';

use Demo\Checkout;

var_dump(Checkout::total("12.50", "3"), Checkout::total(" 0.5 ", null), Checkout::total("1e2", "x"));
var_dump(Checkout::page("7"), Checkout::price("-.25"));

foreach (["12abc", "", "1,5"] as $page) {
    try {
        Checkout::page($page);
    } catch (ValueError $e) {
        echo get_class($e), ': ', $e->getMessage(), ' in ', basename($e->getFile()), ' on line ', $e->getLine(), "\n";
    }
}
try {
    Checkout::page("99999999999999999999");
} catch (ArithmeticError $e) {
    echo get_class($e), ': ', $e->getMessage(), ' in ', basename($e->getFile()), ' on line ', $e->getLine(), "\n";
}
try {
    Checkout::total("NaN", "1");
} catch (ValueError $e) {
    echo get_class($e), ': ', $e->getMessage(), ' in ', basename($e->getFile()), ' on line ', $e->getLine(), "\n";
}
?>
--EXPECT--
int(3750)
int(50)
int(10000)
int(7)
float(-0.25)
ValueError: Sharp\Int::parse(): Argument #1 ($value) must hold an int, '12abc' given in Checkout.sharp on line 47
ValueError: Sharp\Int::parse(): Argument #1 ($value) must hold an int, '' given in Checkout.sharp on line 47
ValueError: Sharp\Int::parse(): Argument #1 ($value) must hold an int, '1,5' given in Checkout.sharp on line 47
ArithmeticError: Sharp\Int::parse(): Argument #1 ($value) must hold an int from PHP_INT_MIN to PHP_INT_MAX, '999999999999999...' given in Checkout.sharp on line 47
ValueError: Sharp\Float::parse(): Argument #1 ($value) must hold a float, 'NaN' given in Checkout.sharp on line 42
