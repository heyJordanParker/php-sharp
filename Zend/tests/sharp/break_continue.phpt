--TEST--
PHP# leaves a loop with break and skips to its next pass with continue
--FILE--
<?php

require __DIR__ . '/ControlFlow.sharp';

echo Demo\ControlFlow::sumOdd(5), "\n";
echo Demo\ControlFlow::sumOdd(0), "\n";
?>
--EXPECT--
9
0
