--TEST--
Json.parse<T?> reads JSON null as null, at the top level, through a type value, as an element and as a field typed T?, as C#'s JsonSerializer.Deserialize<T?> and Rust's serde read it, and a null read into a T that is not nullable stays an Err naming T
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Demo\JsonNulls;

var_dump(JsonNulls::map('null'));
var_dump(count(JsonNulls::map('{"a":1}')));
var_dump(JsonNulls::value(null));
var_dump(count(JsonNulls::elements('[1,null]')), JsonNulls::elements('[1,null]')[1]);
var_dump(JsonNulls::note('{"text":"hi","meta":null}')->meta);

try {
    JsonNulls::required('null');
} catch (LogicException $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
?>
--EXPECT--
NULL
int(1)
NULL
int(2)
NULL
NULL
LogicException: expected Map<string, Any?>, got null
