<?php

require __DIR__ . '/harness/Model.inc';
require __DIR__ . '/harness/TypedModel.inc';
require __DIR__ . '/RushOrders.sharp';
require __DIR__ . '/TypedOrders.sharp';

// Each order is made twice, so the JIT variants compile the methods before the second run.
for ($run = 0; $run < 2; $run++) {
    $order = new Rush\RushOrder();
    echo var_export($order->seen, true), ' | ', $order->tableName(), "\n";
    $typed = new Typed\RushOrder();
    echo var_export($typed->seen, true), ' | ', $typed->tableName(), "\n";
}

// An override runs untyped when a declaration up to the plain PHP root runs untyped, and keeps its type over a typed root.
foreach ([Lib\Model::class, Rush\Order::class, Rush\RushOrder::class, Lib\TypedModel::class, Typed\Order::class, Typed\RushOrder::class] as $class) {
    $property = new ReflectionProperty($class, 'table');
    echo $class, ' hasType: ', var_export($property->hasType(), true), $property->hasType() ? " ({$property->getType()})" : '', "\n";
}
