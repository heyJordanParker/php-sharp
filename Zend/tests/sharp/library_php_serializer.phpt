--TEST--
A PHP# class calls every method of Sharp.Data.PhpSerializer, which allows no classes by default and gives null for text it cannot read or with data after the value, with no warning
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Data/PhpSerializer.sharp';
require __DIR__ . '/PhpSerializerCalls.sharp';

use Demo\PhpSerializerCalls;

var_dump(PhpSerializerCalls::serialize(['a' => 1]), PhpSerializerCalls::serialize(null));
var_dump(PhpSerializerCalls::unserialize('a:1:{s:1:"a";i:1;}'), PhpSerializerCalls::unserialize('b:0;'));
var_dump(get_class(PhpSerializerCalls::unserialize('O:8:"stdClass":0:{}')));
var_dump(get_class(PhpSerializerCalls::unserializeAllowing('O:8:"stdClass":0:{}', ['stdClass'])));
var_dump(PhpSerializerCalls::export([1, 'a' => true]));
var_dump(PhpSerializerCalls::unserialize('nope'));
var_dump(PhpSerializerCalls::unserialize('i:1;junk'));
?>
--EXPECT--
string(18) "a:1:{s:1:"a";i:1;}"
string(2) "N;"
array(1) {
  ["a"]=>
  int(1)
}
bool(false)
string(22) "__PHP_Incomplete_Class"
string(8) "stdClass"
string(34) "array (
  0 => 1,
  'a' => true,
)"
NULL
NULL
