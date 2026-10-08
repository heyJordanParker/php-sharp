--TEST--
PHP# parses request strings with Int and Float, which throw for the .sharp line that calls them or give null
--FILE--
<?php

require __DIR__ . '/../../../sharp/composer/library/Sharp/Int.sharp';
require __DIR__ . '/../../../sharp/composer/library/Sharp/Float.sharp';
require __DIR__ . '/Checkout.sharp';

use Demo\Checkout;

var_dump(Checkout::total("12.50", "3"), Checkout::total(" 0.5 ", null), Checkout::total("1e2", "x"));
var_dump(Checkout::page("7"), Checkout::price("-.25"));

foreach (["12abc", "", "1,5"] as $page) {
    try {
        Checkout::page($page);
    } catch (ValueError $e) {
        echo get_class($e), ': ', $e->getMessage(), ' in ', basename($e->getTrace()[0]['file']), ' on line ', $e->getTrace()[0]['line'], "\n";
    }
}
try {
    Checkout::page("99999999999999999999");
} catch (ArithmeticError $e) {
    echo get_class($e), ': ', $e->getMessage(), ' in ', basename($e->getTrace()[0]['file']), ' on line ', $e->getTrace()[0]['line'], "\n";
}
try {
    Checkout::total("NaN", "1");
} catch (ValueError $e) {
    echo get_class($e), ': ', $e->getMessage(), ' in ', basename($e->getTrace()[0]['file']), ' on line ', $e->getTrace()[0]['line'], "\n";
}
?>
--EXPECT--
int(3750)
int(50)
int(10000)
int(7)
float(-0.25)
ValueError: Int.parse: "12abc" is not an int in Checkout.sharp on line 47
ValueError: Int.parse: "" is not an int in Checkout.sharp on line 47
ValueError: Int.parse: "1,5" is not an int in Checkout.sharp on line 47
ArithmeticError: Int.parse: "99999999999999999999" is out of range for an int in Checkout.sharp on line 47
ValueError: Float.parse: "NaN" is not a float in Checkout.sharp on line 42
