--TEST--
PHP# reads a plain PHP class's property by its name, and its method of that name where the code takes a function the property's type does not fit
--FILE--
<?php
require __DIR__ . '/harness/Gadget.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('SharedNames');

$labels = new SharedNames\Labels();
$gadget = new Lib\Gadget();
var_dump($labels->read($gadget));
var_dump($labels->mapped($gadget));
var_dump($labels->typed($gadget));
var_dump($labels->maybe($gadget), $labels->maybe(null));
var_dump($labels->heldFunction($gadget));
?>
--EXPECT--
string(6) "gadget"
array(2) {
  [0]=>
  string(8) "a gadget"
  [1]=>
  string(8) "b gadget"
}
string(8) "c gadget"
string(8) "d gadget"
NULL
bool(true)
