--TEST--
PHP# `a?.b` and `a?.m()` give null when a is null, and skip the rest of the chain
--FILE--
<?php

require __DIR__ . '/harness/Box.inc';
require __DIR__ . '/Nulls.sharp';

$nulls = new Demo\Nulls;
var_dump(
    $nulls->through(null, 1),
    $nulls->through($nulls, 1),
    $nulls->through($nulls, null),
    $nulls->size(null),
    $nulls->size(new Demo\Box(3)),
);
?>
--EXPECT--
NULL
int(1)
int(7)
NULL
int(3)
