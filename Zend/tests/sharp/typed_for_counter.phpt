--TEST--
PHP# for loops declare their counter with its type written
--FILE--
<?php

require __DIR__ . '/vendor/autoload.php';

$nulls = new Demo\Nulls;
var_dump($nulls->countTo(4), $nulls->countTo(0));
?>
--EXPECT--
int(10)
int(0)
