--TEST--
PHP# reads an all-digit JSON object key into a Map<string, TValue> as the string of its digits, in Json.parse<T>, a struct's generated parse and typeof(T).parse, though PHP's array holds it as an int
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Demo\JsonKeys;

$json = '{"4156": "Launch Lab", "name": "Studio"}';
var_dump(JsonKeys::named($json));
var_dump(JsonKeys::label($json));
var_dump(JsonKeys::options('{"labels": ' . $json . '}'));
var_dump(JsonKeys::decoded(['labels' => [4156 => 'Launch Lab']]));
?>
--EXPECT--
array(2) {
  [0]=>
  string(4) "4156"
  [1]=>
  string(4) "name"
}
string(10) "Launch Lab"
array(2) {
  [0]=>
  string(4) "4156"
  [1]=>
  string(4) "name"
}
array(1) {
  [0]=>
  string(4) "4156"
}
