<?php

require __DIR__ . '/ReadonlyValues.sharp';

use Demo\ReadonlyValues\Ledger;
use Demo\ReadonlyValues\Viewer;

// A constructor's readonly parameter declares no property.
$ledger = new Ledger();
$viewer = new Viewer($ledger);
var_dump(property_exists($viewer, 'ledger'));

// A plain PHP caller passes any Ledger, and may change it between calls.
echo $viewer->render($ledger), "\n";
$ledger->markPaid();
echo $viewer->render($ledger), "\n";
echo $viewer->label([$ledger]), "\n";

// A readonly return is the object itself, which plain PHP may change.
$viewer->current()->paid = 5;
echo $viewer->current()->paid, "\n";

// Reflection shows the type without `readonly`.
echo (new ReflectionMethod(Viewer::class, 'render'))->getParameters()[0]->getType(), "\n";
echo (new ReflectionMethod(Viewer::class, 'current'))->getReturnType(), "\n";

$sum = 0;
for ($i = 0; $i < 200; $i++) {
    $sum += $viewer->render($ledger);
}
echo "sum $sum\n";
