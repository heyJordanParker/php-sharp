--TEST--
Sharp\Bool::tryParse gives true or false for what filter_var reads as a boolean, and null for anything else
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Bool.sharp';

var_dump(Sharp\Bool::tryParse('yes'), Sharp\Bool::tryParse('off'), Sharp\Bool::tryParse('maybe'), Sharp\Bool::tryParse(true));
?>
--EXPECT--
bool(true)
bool(false)
NULL
bool(true)
