--TEST--
PHP# runs if, else if and else
--FILE--
<?php

require __DIR__ . '/ControlFlow.sharp';

foreach ([5, -5, 0] as $value) {
    echo Demo\ControlFlow::sign($value), "\n";
}
?>
--EXPECT--
positive
negative
zero
