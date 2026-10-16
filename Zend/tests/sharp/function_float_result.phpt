--TEST--
An int a plain PHP closure returns becomes a float where PHP# calls it through a function type whose result is a float
--FILE--
<?php
require __DIR__ . '/FloatResults.sharp';

use Floats\FloatResults;

var_dump(FloatResults::direct(fn (int $n) => $n * 2));
var_dump(FloatResults::nullable(fn (int $n) => $n * 2));
var_dump(FloatResults::nullable(fn (int $n) => null));
var_dump(FloatResults::maybe(fn (int $n) => $n * 2));
var_dump(FloatResults::maybe(null));
var_dump(FloatResults::listed(fn () => [1, 2.5]));
var_dump(FloatResults::kept(fn (int $n) => $n * 2));
try {
    FloatResults::direct(fn (int $n) => 'four');
} catch (TypeError $error) {
    echo $error->getMessage(), "\n";
}
?>
--EXPECTF--
float(4)
float(4)
string(4) "none"
float(4)
string(4) "none"
array(2) {
  [0]=>
  float(1)
  [1]=>
  float(2.5)
}
int(4)
f(): Return value must be of type float, string returned
