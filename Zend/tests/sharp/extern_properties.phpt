--TEST--
An extern property of a PHP# class is the one its plain PHP parent's __get gives: PHP declares none, a read reaches __get beside the method of its name, and a value of another class throws TypeError where it enters PHP#
--FILE--
<?php
require __DIR__ . '/harness/Catalog.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('ExternProperties');

$shop = new ExternProperties\Shop();
var_dump(property_exists($shop, 'featured'), $shop->name(), $shop->relation(), $shop->featured->name);
try {
    $shop->misread();
} catch (TypeError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
?>
--EXPECT--
bool(false)
string(8) "featured"
string(21) "the featured relation"
string(8) "featured"
TypeError: ExternProperties.Shop.wrong: Return value must be of type Lib.Item, stdClass returned
