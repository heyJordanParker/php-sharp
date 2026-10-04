--TEST--
PHP# runs for loops with a let counter or with expressions
--FILE--
<?php

require __DIR__ . '/ControlFlow.sharp';

echo Demo\ControlFlow::sumTo(4), "\n";
echo Demo\ControlFlow::sumTo(0), "\n";
?>
--EXPECT--
12
2
