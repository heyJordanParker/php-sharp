--TEST--
PHP# creates an object with new
--FILE--
<?php

require __DIR__ . '/Counter.sharp';
require __DIR__ . '/Shop.sharp';

echo Demo\Shop::run(4), "\n";
?>
--EXPECT--
12
