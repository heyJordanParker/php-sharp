--TEST--
PHP# runs a .sharp class required from PHP, whose / on two ints truncates toward zero and throws DivisionByZeroError for a divisor of 0
--FILE--
<?php

require __DIR__ . '/Calc.sharp';

$calc = new Demo\Calc;
echo $calc->run(1), "\n";
echo $calc->share(7, 2), ' ', $calc->share(-7, 2), "\n";
try {
    $calc->share(7, 0);
} catch (DivisionByZeroError $error) {
    echo get_class($error), ': ', $error->getMessage(), ' on line ', $error->getLine(), "\n";
}
?>
--EXPECT--
22
3 -3
DivisionByZeroError: Division by zero on line 22
