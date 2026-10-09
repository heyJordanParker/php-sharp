--TEST--
A PHP# class overrides a PHP# parent's override: untyped where the plain PHP root is untyped, typed where it is typed
--FILE--
<?php
require __DIR__ . '/property_override_chain.inc';
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
