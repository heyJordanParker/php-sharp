<?php

require __DIR__ . '/harness/Ledger.inc';
require __DIR__ . '/Checkout.sharp';

// Each receipt runs twice, so the JIT variants compile the methods and closures before the second run.
$checkout = new Demo\Checkout(10);
$other = new Demo\Checkout(50);
$ledger = new Lib\Ledger(3);
for ($run = 0; $run < 2; $run++) {
    echo implode(" | ", $checkout->receipt([1000, -5, 250, 4000], $other, $ledger)), "\n";
}

// Plain PHP keeps PHP's rules: a property is not a method, and a method is not a property.
try {
    $ledger->scale(1);
} catch (Error $error) {
    echo $error->getMessage(), "\n";
}
var_dump(@$ledger->record);
