--TEST--
An [Import] result takes a native closure plain PHP built under its stated function type, as an extern result does, and a List result is still checked
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

$untyped = fn (...$arguments) => true;
set_error_handler($untyped);
$previous = Demo\Errors::swap(null);
var_dump($previous === $untyped, $previous(1, 'message', 'file', 2));
var_dump(Demo\Errors::values([3, 4]));
?>
--EXPECT--
bool(true)
bool(true)
array(2) {
  [0]=>
  int(3)
  [1]=>
  int(4)
}
