--TEST--
A PHP# constant's value is built from constants: a ternary, an index, a template, a cast, an enum case, a plain case of an enum whose other cases carry data, a case's value and a lambda, read from PHP# and plain PHP alike, a field's initial value holds a plain case of a data enum, and an attribute argument, a parameter default, an enum case's value and a pattern's value take the same forms
--FILE--
<?php
require __DIR__ . '/harness/Attributes.inc';
require __DIR__ . '/harness/fixture.inc';
require __DIR__ . '/constant_values.inc';
fixture('ConstantValues');

echo constant_values(), "\n";
echo constant_values(), "\n";
?>
--EXPECT--
{"constants":[3,"free","Trial: 14 days",999,true,"free",8,10,3,true,"OutOfRangeException: Undefined array key 9","free tier","3 days"],"plain PHP":[["free","pro"],true,true],"data case":[true,true,true,true,3],"enum cases":["long","pro","trial-14","999","free"],"attribute":["Trial: 14 days","free",true,8,"free",999],"defaults":"3 free Trial: 14 days 999 true free 8","patterns":["grace","price","other",true,true,true,true]}
{"constants":[3,"free","Trial: 14 days",999,true,"free",8,10,3,true,"OutOfRangeException: Undefined array key 9","free tier","3 days"],"plain PHP":[["free","pro"],true,true],"data case":[true,true,true,true,3],"enum cases":["long","pro","trial-14","999","free"],"attribute":["Trial: 14 days","free",true,8,"free",999],"defaults":"3 free Trial: 14 days 999 true free 8","patterns":["grace","price","other",true,true,true,true]}
