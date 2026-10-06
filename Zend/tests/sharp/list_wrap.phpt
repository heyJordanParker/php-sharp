--TEST--
PHP# List.wrap gives a list holding the value, or the list itself
--FILE--
<?php

require __DIR__ . '/Tags.sharp';

var_dump(Demo\Tags::of('sale'));
$tags = ['sale', 'new'];
var_dump(Demo\Tags::of($tags) === $tags);
var_dump(Sharp\List::wrap(null), Sharp\List::wrap(['id' => 7]));
var_dump((new ReflectionClass(Sharp\List::class))->isFinal());
?>
--EXPECT--
array(1) {
  [0]=>
  string(4) "sale"
}
bool(true)
array(1) {
  [0]=>
  NULL
}
array(1) {
  ["id"]=>
  int(7)
}
bool(true)
