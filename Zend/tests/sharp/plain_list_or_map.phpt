--TEST--
Plain PHP's array<V> is a List<V> or a Map<int|string, V>, spec section 12: it keeps the array plain PHP gave, so a list in it stays a list for Json.encode, as and is
--FILE--
<?php
require __DIR__ . '/harness/MessageBag.inc';
require __DIR__ . '/vendor/autoload.php';

echo implode("\n", Arrivals\Messages::read()), "\n";
?>
--EXPECT--
{"price":["The price must be at least 0."]}
map
The price must be at least 0.
