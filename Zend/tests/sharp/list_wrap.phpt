--TEST--
PHP# List.wrap gives a list holding the value, a Map or a Set included, or the list itself
--FILE--
<?php

require __DIR__ . '/Tags.sharp';
require __DIR__ . '/Wraps.sharp';

var_dump(Demo\Tags::of('sale'));
$tags = ['sale', 'new'];
var_dump(Demo\Tags::of($tags) === $tags);
$counts = new Sharp\Map(['a', 1]);
$wrapped = Demo\Wraps::counts($counts);
// The list reaches plain PHP as a PHP array, and the Map or the Set in it as its own PHP array.
echo 'a Map: ', count($wrapped), ' element, ', json_encode($wrapped[0]), "\n";
$marks = new Sharp\Set(['x', 'y']);
$wrapped = Demo\Wraps::marks($marks);
echo 'a Set: ', count($wrapped), ' element, ', json_encode($wrapped[0]), "\n";
var_dump(Sharp\List::wrap(null), Sharp\List::wrap(['id' => 7]));
var_dump((new ReflectionClass(Sharp\List::class))->isFinal());
?>
--EXPECT--
array(1) {
  [0]=>
  string(4) "sale"
}
bool(true)
a Map: 1 element, {"a":1}
a Set: 1 element, ["x","y"]
array(1) {
  [0]=>
  NULL
}
array(1) {
  ["id"]=>
  int(7)
}
bool(true)
