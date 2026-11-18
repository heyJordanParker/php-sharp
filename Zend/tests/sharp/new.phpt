--TEST--
PHP# creates an object with new
--FILE--
<?php

require __DIR__ . '/vendor/autoload.php';

echo Demo\Shop::run(4), "\n";
?>
--EXPECT--
12
