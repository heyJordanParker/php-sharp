--TEST--
A PHP# class filters, maps and sorts a List with lambdas, passes lambdas to plain PHP, calls functions held in properties and uses a method as a value
--FILE--
<?php

require __DIR__ . '/harness/Ledger.inc';
require __DIR__ . '/harness/Probe.inc';
require __DIR__ . '/vendor/autoload.php';

// Each receipt runs twice, so the JIT variants compile the methods and closures before the second run.
$cashier = new Demo\Cashier(10);
$other = new Demo\Cashier(50);
$ledger = new Lib\Ledger(3);
for ($run = 0; $run < 2; $run++) {
    echo implode(" | ", $cashier->receipt([1000, -5, 250, 4000], $other, $ledger)), "\n";
}

// Plain PHP keeps PHP's rules: a property is not a method, and a method is not a property.
try {
    $ledger->scale(1);
} catch (Error $error) {
    echo $error->getMessage(), "\n";
}
var_dump(@$ledger->record);
?>
--EXPECT--
36.00,9.00,2.25 | 4725 | 3 | 1800,450,113 | 14175
36.00,9.00,2.25 | 4725 | 3 | 1800,450,113 | 14175
Call to undefined method Lib\Ledger::scale()
NULL
