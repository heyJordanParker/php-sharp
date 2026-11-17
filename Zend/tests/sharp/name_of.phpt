--TEST--
PHP# nameof(X.y) runs as the string 'y': an instance property, an inherited one, a static one, in an initial value, and as a Map's key and a Set's element
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('NameOf');

$order = new NameOf\Order();
echo $order->sortColumn(), "\n";
echo implode(',', $order->columns()), "\n";
echo $order->width(), "\n";
?>
--EXPECT--
total
total,quantity,count
1
