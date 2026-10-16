--TEST--
A PHP# class calls Sharp.Json.Json: decode gives objects as maps and throws JsonException on invalid JSON, and encode writes a Map as an object and a List as an array, a struct key through its toString(), floats with their fraction, and slashes and Unicode as they are
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Json/Json.sharp';
require __DIR__ . '/harness/fixture.inc';
fixture('JsonCalls');

use Demo\JsonCalls;

var_dump(JsonCalls::decode('{"a":1,"b":[true,null],"c":"x"}'));
var_dump(JsonCalls::decode('null'), JsonCalls::decode('2.5'));

try {
    JsonCalls::decode('{');
} catch (JsonException $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
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
