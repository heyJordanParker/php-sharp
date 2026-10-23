--TEST--
A PHP# class calls Sharp.Encoding.Json: parse gives objects as maps and throws JsonException on invalid JSON, decode<T> reads a struct and a nested struct through T's parse and throws its InvalidFields for a bad field and a ValueError for JSON that holds no object, and encode writes a Map as an object and a List as an array, a struct key through its toString(), floats with their fraction, and slashes and Unicode as they are, and the checker's mark on encode's parameter never reaches the code that runs
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Encoding/Json.sharp';
require __DIR__ . '/../../../sharp/composer/library/Sharp/InvalidFields.sharp';
require __DIR__ . '/harness/fixture.inc';
fixture('JsonCalls');

use Demo\JsonCalls;

var_dump(JsonCalls::parse('{"a":1,"b":[true,null],"c":"x"}'));
var_dump(JsonCalls::parse('null'), JsonCalls::parse('2.5'));

try {
    JsonCalls::parse('{');
} catch (JsonException $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}

var_dump(JsonCalls::point('{"x":3}'));
$order = JsonCalls::order('{"id":7,"tags":["new","vip"],"at":{"x":2}}');
echo get_class($order), ' ', $order->id, ' ', implode(',', $order->tags), ' ', get_class($order->at), ' ', $order->at->x, "\n";
foreach (['{"x":"four"}', '{"other":1}', '3', '{'] as $json) {
    try {
        JsonCalls::point($json);
    } catch (Throwable $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}

foreach (JsonCalls::shapes() as $json) {
    echo $json, "\n";
}
echo JsonCalls::encode(['plain' => [1, 2]]), "\n";

try {
    JsonCalls::untagged();
} catch (JsonException $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}

try {
    JsonCalls::encode(NAN);
} catch (JsonException $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}

// The checker's mark on encode's parameter stays out of the code that runs, where no class declares it.
var_dump((new ReflectionMethod(Sharp\Encoding\Json::class, 'encode'))->getParameters()[0]->getAttributes());
?>
--EXPECTF--
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
object(Demo\JsonPoint)#%d (1) {
  ["x"]=>
  int(3)
}
Demo\JsonOrder 7 new,vip Demo\JsonPoint 2
Sharp\InvalidFields: x: expected int, got string 'four'
Sharp\InvalidFields: x: missing
ValueError: Json.decode: the JSON text holds no object, and a struct or a class is read from one
JsonException: Syntax error
{}
[]
{"counts":{},"lines":[]}
{"0":"a","1":"b"}
{"#red":1}
1.0
"a/é"
{"plain":[1,2]}
JsonException: A JSON object key is text, and Demo\JsonPoint is not
JsonException: Inf and NaN cannot be JSON encoded
array(0) {
}
