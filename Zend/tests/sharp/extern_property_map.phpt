--TEST--
An extern Map property whose plain PHP __get gives a PHP array, as json_decode does, enters PHP# as the Map it declares, spec section 14.3, so a Map member reads it
--FILE--
<?php
require __DIR__ . '/harness/JsonRow.inc';
require __DIR__ . '/vendor/autoload.php';

echo implode("\n", ExternProperties\Answers::read()), "\n";
?>
--EXPECT--
color: red
