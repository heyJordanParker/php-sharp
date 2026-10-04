--TEST--
PHP# keeps the .sharp lines of declarations and runtime errors
--FILE--
<?php

require __DIR__ . '/Calc.sharp';

$class = new ReflectionClass(Demo\Calc::class);
echo 'class ', $class->getStartLine(), '-', $class->getEndLine(), "\n";

$run = $class->getMethod('run');
echo 'run ', $run->getStartLine(), '-', $run->getEndLine(), "\n";

try {
    Demo\Calc::make(PHP_INT_MAX);
} catch (TypeError $e) {
    echo $e->getMessage(), ' on line ', $e->getLine(), "\n";
}
?>
--EXPECT--
class 3-21
run 15-20
Demo\Calc::make(): Return value must be of type int, float returned on line 7
