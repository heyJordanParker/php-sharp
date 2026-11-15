--TEST--
A PHP# class overrides a plain PHP parent's properties: untyped where the parent's is untyped, typed where it is typed
--FILE--
<?php
require __DIR__ . '/property_override.inc';
?>
--EXPECT--
orders fills number,total | orders | 20 | false
orders fills number,total | orders | 20 | false
table: untyped, protected, 1 Override
fillable: untyped, protected, 1 Override
with: untyped, public, 1 Override
timestamps: untyped, public, 1 Override
perPage: int, public, 1 Override
customer,lines | 50
3 parts
int(5)
Store\Order::tableName(): Return value must be of type string, int returned
Cannot assign string to property Store\Order::$perPage of type int
