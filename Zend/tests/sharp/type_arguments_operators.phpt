--TEST--
A value of a type parameter runs the operator its bound declares, and plain PHP keeps its own operator on objects
--FILE--
<?php
require __DIR__ . '/TypeArgumentsOperators.sharp';

use Operators\Ledger;
use Operators\Money;

/**
 * @template T of Money
 * @param T $a
 * @param T $b
 */
function plain_sum(Money $a, Money $b): Money
{
    return $a + $b;
}

echo Ledger::total(new Money(2), new Money(3)), "\n";
echo Ledger::sum(new Money(4), new Money(5))->cents, "\n";
try {
    plain_sum(new Money(2), new Money(3));
} catch (TypeError $error) {
    echo $error->getMessage(), "\n";
}
?>
--EXPECT--
5
9
Unsupported operand types: Operators\Money + Operators\Money
