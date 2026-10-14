--TEST--
PHP# `a?.b`, `a?.m()` and `f?.(x)` give null when a or f is null, and skip the rest of the chain
--FILE--
<?php

require __DIR__ . '/harness/Box.inc';
require __DIR__ . '/Nulls.sharp';

$nulls = new Demo\Nulls;
$double = fn (int $n): int => $n * 2;
var_dump(
    $nulls->through(null, 1),
    $nulls->through($nulls, 1),
    $nulls->through($nulls, null),
    $nulls->size(null),
    $nulls->size(new Demo\Box(3)),
    $nulls->local(null, 4),
    $nulls->local($double, 4),
    $nulls->property(null, 5),
    $nulls->property($double, 5),
    $nulls->called(null, 6),
    $nulls->called($double, 6),
);
?>
--EXPECT--
NULL
int(1)
int(7)
NULL
int(3)
NULL
int(8)
NULL
int(10)
NULL
int(12)
