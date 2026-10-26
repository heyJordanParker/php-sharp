--TEST--
A PHP# class calls Sharp.Encoding.Serialized, which allows no classes by default and gives null for text it cannot read or with data after the value, with no warning, and Sharp.Encoding.PhpLiteral
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Encoding/Serialized.sharp';
require __DIR__ . '/../../../sharp/composer/library/Sharp/Encoding/PhpLiteral.sharp';
require __DIR__ . '/../../../sharp/composer/library/Sharp/ListMembers.sharp';
require __DIR__ . '/SerializedCalls.sharp';

use Demo\SerializedCalls;

var_dump(SerializedCalls::encode(['a' => 1]), SerializedCalls::encode(null));
var_dump(SerializedCalls::decode('a:1:{s:1:"a";i:1;}'), SerializedCalls::decode('b:0;'));
var_dump(get_class(SerializedCalls::decode('O:8:"stdClass":0:{}')));
var_dump(get_class(SerializedCalls::decodeAllowing('O:8:"stdClass":0:{}', ['stdClass'])));
var_dump(SerializedCalls::literal([1, 'a' => true]));
var_dump(SerializedCalls::decode('nope'));
var_dump(SerializedCalls::decode('i:1;junk'));
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
