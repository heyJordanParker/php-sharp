--TEST--
PHP# runs for … of loops over the values, or the keys and values, of a PHP array
--FILE--
<?php

namespace Demo;

require __DIR__ . '/harness/Prices.inc';
require __DIR__ . '/ControlFlow.sharp';

echo ControlFlow::weigh(2), "\n";
?>
--EXPECT--
44
