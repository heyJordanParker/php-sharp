--TEST--
PHP# runs for … of loops over the elements of a List, and the keys and values of a Map, each a PHP array
--FILE--
<?php

namespace Demo;

require __DIR__ . '/harness/Prices.inc';
require __DIR__ . '/vendor/autoload.php';

echo ControlFlow::weigh(2), "\n";
?>
--EXPECT--
44
