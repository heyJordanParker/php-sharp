--TEST--
PHP# `a ?? b` gives b when a is null
--FILE--
<?php

require __DIR__ . '/Nulls.sharp';

$nulls = new Demo\Nulls;
var_dump($nulls->either(1, 2), $nulls->either(null, 2), $nulls->either(null, null), $nulls->either(0, 2));
?>
--EXPECT--
int(1)
int(2)
int(0)
int(0)
