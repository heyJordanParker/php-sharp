--TEST--
Sharp\Bool::tryParse gives true or false for what filter_var reads as a boolean, and null for anything else
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

var_dump(Sharp\Bool::parse('yes')->ok(), Sharp\Bool::parse('off')->ok(), Sharp\Bool::parse('maybe')->ok(), Sharp\Bool::parse(true)->ok());
?>
--EXPECT--
bool(true)
bool(false)
NULL
bool(true)
