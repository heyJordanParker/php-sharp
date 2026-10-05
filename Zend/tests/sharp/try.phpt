--TEST--
PHP# catches an exception by any of its classes, and runs finally on every way out
--FILE--
<?php

require __DIR__ . '/harness/Box.inc';
require __DIR__ . '/Expressions.sharp';

$steps = new Demo\Box(0);
var_dump(Demo\Expressions::recover(5, $steps), $steps->size);

$steps = new Demo\Box(0);
try {
    Demo\Expressions::recover(-1, $steps);
} catch (RuntimeException $e) {
    echo get_class($e), ': ', $e->getMessage(), ' on line ', $e->getLine(), ', from ', get_class($e->getPrevious()), "\n";
}
var_dump($steps->size);
?>
--EXPECT--
int(5)
int(101)
RuntimeException: recovered on line 31, from InvalidArgumentException
int(111)
