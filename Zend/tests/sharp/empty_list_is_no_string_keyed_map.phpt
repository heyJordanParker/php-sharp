--TEST--
[] is the empty List, spec section 12, so an is test of it against a Map of string keys is false and it never takes a Map branch, while a typed boundary takes plain PHP's [] as the empty Map it declares
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

echo Arrivals\EmptyTests::run(), "\n";
var_dump(Arrivals\EmptyTests::count([]));
?>
--EXPECT--
json[]:not a map json{}:map {} json{}isList:false made[]:not a map
int(0)
