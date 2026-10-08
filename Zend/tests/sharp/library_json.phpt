--TEST--
A PHP# class calls Sharp.Json.Json.decode, which gives objects as maps and throws JsonException on invalid JSON
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Json/Json.sharp';
require __DIR__ . '/JsonCalls.sharp';

use Demo\JsonCalls;

var_dump(JsonCalls::decode('{"a":1,"b":[true,null],"c":"x"}'));
var_dump(JsonCalls::decode('null'), JsonCalls::decode('2.5'));

try {
    JsonCalls::decode('{');
} catch (JsonException $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
?>
--EXPECT--
array(3) {
  ["a"]=>
  int(1)
  ["b"]=>
  array(2) {
    [0]=>
    bool(true)
    [1]=>
    NULL
  }
  ["c"]=>
  string(1) "x"
}
NULL
float(2.5)
JsonException: Syntax error
