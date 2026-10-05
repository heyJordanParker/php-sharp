--TEST--
A PHP# entity holds state through fields, properties, initial values and its constructor
--FILE--
<?php

require __DIR__ . '/Order.sharp';

echo (new Demo\Till())->ring(), "\n";

$order = new Demo\Order(9, 3, 'B7');
echo $order->id, ' ', $order->code, ' ', $order->total, ' ', $order->add(1), "\n";

try {
    $order->total = 0;
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
11
9 B7 0 12
Cannot modify private(set) property Demo\Order::$total from global scope
