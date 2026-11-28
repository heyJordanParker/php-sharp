--TEST--
Json.parse<T> and Json.encode, spec section 10, read a property a parent's constructor sets through the main constructor's `: super(…)` once, by the parameter of its name, so the main constructor builds it
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use InheritedFields\Uploads;

echo Uploads::decoded(), "\n";
echo Uploads::encoded(), "\n";
?>
--EXPECT--
https://a.test|
{"url":{"value":"https://a.test"}}
