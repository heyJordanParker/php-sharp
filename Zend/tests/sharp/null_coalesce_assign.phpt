--TEST--
PHP# `a ??= b` assigns b only when a is null
--FILE--
<?php

require __DIR__ . '/Nulls.sharp';

$nulls = new Demo\Nulls;
var_dump($nulls->fill(null), $nulls->fill(2));
?>
--EXPECT--
int(15)
int(4)
