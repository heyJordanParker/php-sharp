--TEST--
PHP# nameof(X.y) runs as the string 'y': an instance property, an inherited one, a static one, and in an initial value
--FILE--
<?php
require __DIR__ . '/NameOf.sharp';

$order = new NameOf\Order();
echo $order->sortColumn(), "\n";
echo implode(',', $order->columns()), "\n";
?>
--EXPECT--
total
total,quantity,count
