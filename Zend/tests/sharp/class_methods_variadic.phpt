--TEST--
Class<T>.methods() reads a method whose last parameter is variadic, which PHP keeps after its other parameters, and gives that parameter by its name
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('VariadicMethods');

var_dump(App\Rest\Rest::parameters());
?>
--EXPECT--
array(1) {
  [0]=>
  string(5) "parts"
}
