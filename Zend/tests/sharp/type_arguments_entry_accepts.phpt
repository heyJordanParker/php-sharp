--TEST--
Sharp\Internal\accepts, which a struct's parse calls, collects every bad value at its path through the walk that checks a value entering PHP#
--FILE--
<?php
require __DIR__ . '/type_arguments_entry.inc';

$problems = [];
$value = ['lines' => [1, 'two', str_repeat('x', 45)], 'prices' => ['vip' => 'cheap', 3 => 1]];
var_dump(\Sharp\Internal\accepts('Map<string, List<int>>', $value, 'order', $problems));
var_dump($problems);
$problems = [];
$value = ['vip' => 1, 'base' => 2.5];
$shared = $value;
var_dump(\Sharp\Internal\accepts('Map<string, float>', $value, 'prices', $problems), $problems, $value['vip'], $shared['vip']);
$problems = [];
$value = ['vip' => 1];
var_dump(\Sharp\Internal\accepts('Map<int, int>?', $value, 'counts', $problems), $problems);
// A class value names its class or a class whose header fixes the type arguments, and a name carries no others.
$problems = [];
$value = ['page' => 'App\OrderPage', 'raw' => 'App\PaginatedList', 'none' => 7];
var_dump(\Sharp\Internal\accepts('Map<string, Class<App.PaginatedList<App.Order>>>', $value, 'kinds', $problems), $problems);
foreach (['List<', 'List<$0>'] as $type) {
    try {
        \Sharp\Internal\accepts($type, $value, 'x', $problems);
    } catch (ValueError $error) {
        echo $error->getMessage(), "\n";
    }
}
?>
--EXPECTF--
bool(false)
array(3) {
  ["order["lines"][1]"]=>
  string(30) "expected int, got string 'two'"
  ["order["lines"][2]"]=>
  string(%d) "expected int, got string 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx…'"
  ["order["prices"]"]=>
  string(27) "expected List<int>, got Map"
}
bool(true)
array(0) {
}
float(1)
int(1)
bool(false)
array(1) {
  ["counts["vip"]"]=>
  string(34) "key expected int, got string 'vip'"
}
bool(false)
array(2) {
  ["kinds["raw"]"]=>
  string(76) "expected Class<App.PaginatedList<App.Order>>, got string 'App\PaginatedList'"
  ["kinds["none"]"]=>
  string(55) "expected Class<App.PaginatedList<App.Order>>, got int 7"
}
Sharp\Internal\accepts(): Argument #1 ($type) must be one PHP# type
Sharp\Internal\accepts(): Argument #1 ($type) must name only type parameters the calling code has type arguments for
