<?php

require __DIR__ . '/harness/Model.inc';
require __DIR__ . '/vendor/autoload.php';

// Each order is made twice, so the JIT variants compile the methods before the second run.
for ($run = 0; $run < 2; $run++) {
    $order = new Store\Order();
    echo $order->seen, ' | ', $order->tableName(), ' | ', $order->pageSize(), ' | ', var_export($order->timestamps, true), "\n";
}

// An override of a property Model declares untyped runs untyped, as its PHP twin does, and one Model types keeps it.
foreach (['table', 'fillable', 'with', 'timestamps', 'perPage'] as $name) {
    $property = new ReflectionProperty(Store\Order::class, $name);
    echo $name, ': ', $property->hasType() ? $property->getType() : 'untyped', ', ',
        implode(' ', Reflection::getModifierNames($property->getModifiers())), ', ',
        count($property->getAttributes(Override::class)), " Override\n";
}

// An override widens Model's `protected` to `public`, so code outside reads it. Its `set` keeps Model's, which PHP
// refuses to narrow, so code outside writes it too.
$order->with = ['customer', 'lines'];
$order->perPage = 50;
echo implode(',', $order->with), ' | ', $order->perPage, "\n";
$order->perPage = 20;

// An override that writes Model's variadic parameter as its `List` is still variadic.
echo $order->labelled('a', 'b', 'c'), "\n";

// Model's own code may store any value in the untyped property, and the PHP# method still checks what it returns.
$order->setTable(5);
var_dump((fn () => $this->table)->call($order));
try {
    $order->tableName();
} catch (TypeError $error) {
    echo $error->getMessage(), "\n";
}
try {
    $order->setPerPage('many');
} catch (TypeError $error) {
    echo $error->getMessage(), "\n";
}
