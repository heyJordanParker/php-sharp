--TEST--
PHP# runs a .sharp class required from PHP
--FILE--
<?php

require __DIR__ . '/Calc.sharp';

echo (new Demo\Calc)->run(1), "\n";
?>
--EXPECT--
22
