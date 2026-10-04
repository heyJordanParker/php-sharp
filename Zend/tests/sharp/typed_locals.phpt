--TEST--
PHP# locals declared with their type written run as plain PHP variables
--FILE--
<?php

require __DIR__ . '/Nulls.sharp';

$nulls = new Demo\Nulls;
var_dump($nulls->typed(null), $nulls->typed(5));
?>
--EXPECT--
int(11)
int(15)
