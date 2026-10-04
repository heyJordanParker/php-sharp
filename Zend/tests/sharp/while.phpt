--TEST--
PHP# runs while and do … while, whose body runs at least once
--FILE--
<?php

require __DIR__ . '/ControlFlow.sharp';

echo Demo\ControlFlow::countdown(3), "\n";
echo Demo\ControlFlow::countdown(0), "\n";
?>
--EXPECT--
13
10
