--TEST--
PHP# constant values the preload request already read, a lambda and a plain case of an enum whose other cases carry data among them, give the same values in each request, as attribute arguments, parameter defaults, enum case values and pattern values do
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/constant_values_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(ConstantValues\Billing::class, false));
echo constant_values(), "\n";
echo constant_values(), "\n";
?>
--EXPECT--
bool(true)
{"constants":[3,"free","Trial: 14 days",999,true,"free",8,10,3,true,"OutOfRangeException: Undefined array key 9","free tier","3 days"],"plain PHP":[["free","pro"],true,true],"data case":[true,true,true,true,3],"enum cases":["long","pro","trial-14","999","free"],"attribute":["Trial: 14 days","free",true,8,"free",999],"defaults":"3 free Trial: 14 days 999 true free 8","patterns":["grace","price","other",true,true,true,true]}
{"constants":[3,"free","Trial: 14 days",999,true,"free",8,10,3,true,"OutOfRangeException: Undefined array key 9","free tier","3 days"],"plain PHP":[["free","pro"],true,true],"data case":[true,true,true,true,3],"enum cases":["long","pro","trial-14","999","free"],"attribute":["Trial: 14 days","free",true,8,"free",999],"defaults":"3 free Trial: 14 days 999 true free 8","patterns":["grace","price","other",true,true,true,true]}
