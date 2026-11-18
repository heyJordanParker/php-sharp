--TEST--
A PHP# class overrides a PHP# parent's override: untyped where the plain PHP root is untyped, typed where it is typed
--FILE--
<?php

require __DIR__ . '/harness/Model.inc';
require __DIR__ . '/harness/TypedModel.inc';
require __DIR__ . '/vendor/autoload.php';

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
?>
--EXPECT--
'rush_orders fills ' | rush_orders
'rush_orders' | rush_orders
'rush_orders fills ' | rush_orders
'rush_orders' | rush_orders
Lib\Model hasType: false
Rush\Order hasType: false
Rush\RushOrder hasType: false
Lib\TypedModel hasType: true (string)
Typed\Order hasType: true (string)
Typed\RushOrder hasType: true (string)
