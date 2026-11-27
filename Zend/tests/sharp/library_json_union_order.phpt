--TEST--
Json.decode<T> reads a value into a union through its members in the order the type argument writes them, spec section 10, through a generic wrapper's type argument too, and the written order never changes a type test
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Demo\JsonUnions;

function show(iterable $values): void
{
    foreach ($values as $key => $value) {
        echo $key, ': ', get_debug_type($value), ' ', var_export($value, true), "\n";
    }
}

show(JsonUnions::scalars('{"step_id":3006,"share":2.5}'));
show(JsonUnions::floatsFirst('{"step_id":3006,"share":2.5}'));
show(JsonUnions::wrapped('[3006,2.5]'));
var_dump(JsonUnions::sameType());
?>
--EXPECT--
step_id: int 3006
share: float 2.5
step_id: float 3006.0
share: float 2.5
0: int 3006
1: float 2.5
bool(true)
